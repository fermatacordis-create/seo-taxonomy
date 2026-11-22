"""Regression checks for the gated STAGING -> official Plugin Check -> PRO workflow.

These tests are deliberately dependency-free and make no network calls.
"""
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
PIPELINE = ROOT / ".github/workflows/staging_check_subida_a_pro.yml"
STAGING = ROOT / ".github/workflows/deploy-staging.yml"
CHECK = ROOT / ".github/workflows/taxocommerce-plugin-check.yml"
PRO = ROOT / ".github/workflows/despliege_de_main_a_pro.yml"


class GatedPipelineContract(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.pipeline = PIPELINE.read_text(encoding="utf-8")
        cls.staging = STAGING.read_text(encoding="utf-8")
        cls.check = CHECK.read_text(encoding="utf-8")
        cls.pro = PRO.read_text(encoding="utf-8")

    def test_staging_automatic_and_three_phase_only_with_manual_pro_approval(self):
        self.assertIn("workflow_dispatch:", self.pipeline)
        self.assertNotIn("  push:", self.pipeline)
        self.assertIn("inputs.deploy_pro == true", self.pipeline)
        self.assertIn("refs/heads/staging", self.pipeline)
        # A long-running Plugin Check must NOT block newer staging uploads.
        pipeline_header = self.pipeline.split("\njobs:\n", 1)[0]
        self.assertNotIn("\nconcurrency:\n", pipeline_header)
        self.assertIn("concurrency:", self.staging)
        self.assertIn("taxocommerce-site-delivery", self.staging)
        self.assertIn("cancel-in-progress: false", self.staging)
        self.assertIn("git ls-remote origin refs/heads/staging", self.pipeline)
        self.assertIn("needs.preflight.outputs.current == 'true'", self.pipeline)
        # The staging-only workflow owns all automatic merges.
        self.assertIn("  push:", self.staging)
        self.assertIn("branches: [staging]", self.staging)
        self.assertIn("workflow_call:", self.staging)
        self.assertNotIn("TAXOCOMMERCE_REMOTE_VERIFY: '1'", self.staging)
        self.assertIn("STAGING_FTP_PASSWORD:", self.staging)
        self.assertIn("TAXOCOMMERCE_TARGET_PLUGIN:", self.staging)
        self.assertNotIn("github.workflow == 'Immediate deployment to STAGING'", self.staging)
        # Uploading changed plugin files must never be blocked by optional
        # WordPress login credentials or automatic plugin activation.
        self.assertNotIn("python3 .github/scripts/activate_accounting_staging.py", self.staging)
        self.assertNotIn("Activate and verify new Accounting plugin", self.staging)
        self.assertNotIn("remote_plugin_entrypoint_drift", self.staging)



    def test_plugin_check_does_not_monopolize_the_ftp_delivery_lock(self):
        # Only the STAGING/Focazul-only workflow serializes the shared FTPS
        # account; the manual WordPress.org check remains separate.
        self.assertNotIn("taxocommerce-staging-check-pro\n", self.pipeline)
        self.assertIn("uses: ./.github/workflows/deploy-staging.yml", self.pipeline)
        self.assertIn("needs: staging", self.pipeline)
        self.assertIn("needs: [staging, plugin-check]", self.pipeline)
        self.assertIn("taxocommerce-site-delivery", self.staging)
        self.assertIn("cancel-in-progress: false", self.staging)

    def test_staging_and_both_manual_pro_workflows_share_a_release_mutex(self):
        alternate = (ROOT / ".github/workflows/staging-a-pro-manual.yml").read_text(encoding="utf-8")
        self.assertIn("taxocommerce-site-delivery", self.staging)
        self.assertIn("taxocommerce-site-delivery", self.pro)
        self.assertIn("taxocommerce-site-delivery", alternate)
        self.assertIn("cancel-in-progress: false", self.staging)
        self.assertIn("cancel-in-progress: false", self.pro)
        self.assertIn("cancel-in-progress: false", alternate)
        self.assertIn("if: needs.plan.outputs.core == 'true'", self.staging)

    def test_ftps_is_serial_across_sites_but_focazul_runs_after_staging_failure(self):
        # One shared FTP account; failures are still reported independently.
        focazul = self.staging.split("  deploy-focazul:", 1)[1].split("  verify:", 1)[0]
        self.assertIn("needs: [plan, deploy-staging]", focazul)
        self.assertIn("always() && needs.plan.result == 'success'", focazul)
        self.assertIn("max-parallel: 1", focazul)
        self.assertIn("--target focazul", focazul)
        self.assertIn("python3 -u .github/scripts/deploy_staging_delta.py", focazul)
        self.assertNotIn("for attempt in 1 2 3", focazul)
        staging = self.staging.split("  deploy-staging:", 1)[1].split("  deploy-focazul:", 1)[0]
        self.assertIn("max-parallel: 1", staging)
        self.assertIn("--target staging", staging)
        self.assertNotIn("for attempt in 1 2 3", staging)
        self.assertIn('test "$FOCAZUL" = "success"', self.staging)
        self.assertIn('test "$STAGING" = "success"', self.staging)

    def test_order_and_strict_family_check(self):
        self.assertIn("uses: ./.github/workflows/deploy-staging.yml", self.pipeline)
        self.assertIn("uses: ./.github/workflows/taxocommerce-plugin-check.yml", self.pipeline)
        self.assertIn("uses: ./.github/workflows/despliege_de_main_a_pro.yml", self.pipeline)
        self.assertIn("plugin: ${{ inputs.target_plugin || 'changed' }}", self.pipeline)
        self.assertIn("needs: staging", self.pipeline)
        self.assertIn("needs: [staging, plugin-check]", self.pipeline)
        self.assertIn("needs.plugin-check.result == 'success'", self.pipeline)
        self.assertIn("target_plugin: ${{ inputs.target_plugin || '' }}", self.pipeline)
        self.assertIn("source_sha:", self.pipeline)
        self.assertIn("ignore-warnings: false", self.check)
        self.assertIn("ignore-errors: false", self.check)
        self.assertIn("strict: true", self.check)

    def test_existing_workflows_are_reusable_without_removing_manual_execution(self):
        for workflow in (self.staging, self.check, self.pro):
            self.assertIn("workflow_call:", workflow)
            self.assertIn("workflow_dispatch:", workflow)
        self.assertIn('if os.environ.get("REQUESTED_PLUGIN") == "all":', self.check)
        self.assertIn("max-parallel: 2", self.check)

    def test_pro_rejects_moving_staging_and_preserves_verification(self):
        self.assertIn('test "$GITHUB_REF_NAME" = "staging"', self.pro)
        self.assertIn('ACTUAL_SHA="$(git rev-parse HEAD)"', self.pro)
        self.assertIn('if [ "$ACTUAL_SHA" != "$EXPECTED_SHA" ]; then', self.pro)
        # PRO's reusable workflow requires actions:read to find the last safe SHA.
        production = self.pipeline.split("  production:", 1)[1].split("  report-failure:", 1)[0]
        self.assertIn("      actions: read", production)
        uploader = (ROOT / ".github/scripts/deploy_pro_ftps_native.py").read_text(encoding="utf-8")
        self.assertIn("hashlib.sha256", uploader)
        self.assertIn("Uploaded file size mismatch", uploader)
        self.assertNotIn("retrbinary(", uploader)

    def test_failure_opens_issue_and_never_proceeds_to_pro(self):
        self.assertIn("report-failure:", self.pipeline)
        self.assertIn("gh issue create", self.pipeline)
        self.assertIn("issues: write", self.pipeline)
        self.assertIn("repair required", self.pipeline)
        self.assertIn("COPILOT_AGENT_TOKEN", self.pipeline)
        self.assertIn("copilot-swe-agent[bot]", self.pipeline)
        self.assertIn("PRO needs fresh explicit manual approval", self.pipeline)
        self.assertNotIn("for attempt in 1 2 3", self.staging)
        self.assertIn("python3 -u .github/scripts/deploy_staging_delta.py", self.staging)
        retry_source = (ROOT / ".github/scripts/deploy_staging_delta.py").read_text(encoding="utf-8")
        self.assertIn("for attempt in range(3)", retry_source)
        self.assertIn('phase = "connect"', retry_source)
        self.assertNotIn("gh pr merge", self.pipeline)
        self.assertNotIn("gh workflow run", self.pipeline)


if __name__ == "__main__":
    unittest.main()
