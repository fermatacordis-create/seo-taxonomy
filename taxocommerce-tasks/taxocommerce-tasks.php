<?php
/**
 * Plugin Name: TaxoCommerce Tasks
 * Plugin URI: https://asociacionfocazul.com/taxocommerce-tasks/
 * Description: Private, standalone WordPress task agenda with calendar and dashboard.
 * Version: 0.1.18
 * Release Date: 2026-11-13
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * Author: David Perez
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: taxocommerce-tasks
 * Domain Path: /languages
 */
defined( 'ABSPATH' ) || exit;

define( 'TAXOCOMMERCE_TASKS_VERSION', '0.1.18' );
define( 'TAXOCOMMERCE_TASKS_RELEASE_DATE', '2026-11-10' );

require_once __DIR__ . '/includes/class-tctasks-manuals.php';
require_once __DIR__ . '/includes/class-tctasks-calendar.php';
// Standalone family-wide visual header; no dependency on TaxoCommerce Core.
require_once __DIR__ . '/includes/admin-branding.php';
require_once __DIR__ . '/includes/class-taxocommerce-autotest-code.php';
\TaxoCommerce_Tasks_Autotest_Code::boot();
add_action( 'admin_enqueue_scripts', array( 'TCTasks_Calendar', 'enqueue_assets' ) );

/** Translate admin labels using WordPress gettext; native language packs override the defaults. */
function tctasks_label( $source ) {
    $locale = determine_locale();
    $lang   = substr( $locale, 0, 2 );
    $labels = array(
        'es' => array(
            'User manual' => 'Manual de usuario', 'Technical manual' => 'Manual técnico',
            'New category' => 'Nueva categoría', 'Edit category' => 'Editar categoría',
            'Update category' => 'Guardar cambios', 'Category updated.' => 'Categoría actualizada.',
            'Category not found.' => 'Categoría no encontrada.',
            'Unable to save category. Name must be unique.' => 'No se ha podido guardar. El nombre debe ser único.',
            'Renaming a category preserves the tasks assigned to it.' => 'Cambiar el nombre conserva las tareas asignadas.',
            'Cancel edit' => 'Cancelar edición',
            'Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado','Sunday'=>'Domingo',
            'Tasks' => 'Tareas', 'Calendar' => 'Calendario', 'Dashboard' => 'Indicadores', 'Categories' => 'Categorías',
            'Autocheck' => 'Autocheck', 'New task' => 'Nueva tarea', 'Edit task' => 'Editar tarea', 'Title' => 'Título',
            'Description' => 'Descripción', 'Category' => 'Categoría', 'Priority' => 'Prioridad', 'Status' => 'Estado',
            'Assignee' => 'Responsable', 'Start date' => 'Fecha de inicio', 'Due date' => 'Fecha límite',
            'Completed at' => 'Finalizada', 'Save task' => 'Guardar tarea', 'Delete' => 'Eliminar', 'Edit' => 'Editar',
            'All' => 'Todas', 'Filter' => 'Filtrar', 'No tasks found.' => 'No hay tareas.',
            'Back' => 'Volver', 'Previous' => 'Anterior', 'Next' => 'Siguiente', 'Save category' => 'Guardar categoría',
            'Category name' => 'Nombre de categoría', 'No category' => 'Sin categoría', 'Unassigned' => 'Sin asignar',
            'Low' => 'Baja', 'Normal' => 'Normal', 'High' => 'Alta', 'Urgent' => 'Urgente',
            'Pending' => 'Pendiente', 'In progress' => 'En curso', 'Blocked' => 'Bloqueada', 'Completed' => 'Completada',
            'Total' => 'Total', 'Overdue' => 'Vencidas', 'By status' => 'Por estado', 'By priority' => 'Por prioridad',
            'By assignee' => 'Por responsable', 'Download JSON' => 'Descargar JSON',
            'Run autocheck' => 'Ejecutar Autocheck', 'Last executed' => 'Última ejecución',
            'Name' => 'Nombre', 'Actions' => 'Acciones', 'Task saved.' => 'Tarea guardada.',
            'Task deleted.' => 'Tarea eliminada.', 'Category saved.' => 'Categoría guardada.',
            'Category deleted.' => 'Categoría eliminada.', 'Cannot delete a category that has tasks.' => 'No se puede eliminar una categoría que tiene tareas.',
            'Task agenda' => 'Agenda de tareas', 'Private task management, calendar and indicators.' => 'Gestión privada de tareas, calendario e indicadores.',
            'All statuses' => 'Todos los estados', 'All priorities' => 'Todas las prioridades',
            'All categories' => 'Todas las categorías', 'All assignees' => 'Todos los responsables',
            'Cancel' => 'Cancelar', 'Only registered WordPress users can be assigned.' => 'Solo se pueden asignar usuarios registrados de WordPress.',
        ),
        'fr' => array(
            'User manual' => 'Manuel utilisateur', 'Technical manual' => 'Manuel technique',
            'New category' => 'Nouvelle catégorie', 'Edit category' => 'Modifier la catégorie',
            'Update category' => 'Enregistrer les modifications', 'Category updated.' => 'Catégorie mise à jour.',
            'Category not found.' => 'Catégorie introuvable.',
            'Unable to save category. Name must be unique.' => 'Enregistrement impossible. Le nom doit être unique.',
            'Renaming a category preserves the tasks assigned to it.' => 'Renommer une catégorie conserve ses tâches.',
            'Cancel edit' => 'Annuler la modification',
            'Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi','Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi','Sunday'=>'Dimanche',
            'Tasks'=>'Tâches','Calendar'=>'Calendrier','Dashboard'=>'Indicateurs','Categories'=>'Catégories','Autocheck'=>'Autocontrôle',
            'New task'=>'Nouvelle tâche','Edit task'=>'Modifier la tâche','Title'=>'Titre','Description'=>'Description',
            'Category'=>'Catégorie','Priority'=>'Priorité','Status'=>'Statut','Assignee'=>'Responsable',
            'Start date'=>'Date de début','Due date'=>'Échéance','Completed at'=>'Terminée le','Save task'=>'Enregistrer',
            'Delete'=>'Supprimer','Edit'=>'Modifier','All'=>'Tous','Filter'=>'Filtrer','No tasks found.'=>'Aucune tâche.',
            'Back'=>'Retour','Previous'=>'Précédent','Next'=>'Suivant','Save category'=>'Enregistrer la catégorie',
            'Category name'=>'Nom de catégorie','No category'=>'Sans catégorie','Unassigned'=>'Non attribué',
            'Low'=>'Faible','Normal'=>'Normale','High'=>'Élevée','Urgent'=>'Urgente','Pending'=>'En attente',
            'In progress'=>'En cours','Blocked'=>'Bloquée','Completed'=>'Terminée','Total'=>'Total','Overdue'=>'En retard',
            'By status'=>'Par statut','By priority'=>'Par priorité','By assignee'=>'Par responsable',
            'Download JSON'=>'Télécharger JSON','Run autocheck'=>'Exécuter l’autocontrôle','Last executed'=>'Dernière exécution',
            'Name'=>'Nom','Actions'=>'Actions','Task saved.'=>'Tâche enregistrée.','Task deleted.'=>'Tâche supprimée.',
            'Category saved.'=>'Catégorie enregistrée.','Category deleted.'=>'Catégorie supprimée.',
            'Cannot delete a category that has tasks.'=>'Impossible de supprimer une catégorie utilisée.',
            'Task agenda'=>'Agenda des tâches','Private task management, calendar and indicators.'=>'Gestion privée des tâches, calendrier et indicateurs.',
            'All statuses'=>'Tous les statuts','All priorities'=>'Toutes les priorités',
            'All categories'=>'Toutes les catégories','All assignees'=>'Tous les responsables',
            'Cancel'=>'Annuler','Only registered WordPress users can be assigned.'=>'Seuls les utilisateurs WordPress peuvent être désignés.',
        ),
        'de' => array(
            'User manual' => 'Benutzerhandbuch', 'Technical manual' => 'Technisches Handbuch',
            'New category' => 'Neue Kategorie', 'Edit category' => 'Kategorie bearbeiten',
            'Update category' => 'Änderungen speichern', 'Category updated.' => 'Kategorie aktualisiert.',
            'Category not found.' => 'Kategorie nicht gefunden.',
            'Unable to save category. Name must be unique.' => 'Speichern fehlgeschlagen. Der Name muss eindeutig sein.',
            'Renaming a category preserves the tasks assigned to it.' => 'Das Umbenennen erhält die Aufgaben-Zuordnungen.',
            'Cancel edit' => 'Bearbeitung abbrechen',
            'Monday'=>'Montag','Tuesday'=>'Dienstag','Wednesday'=>'Mittwoch','Thursday'=>'Donnerstag','Friday'=>'Freitag','Saturday'=>'Samstag','Sunday'=>'Sonntag',
            'Tasks'=>'Aufgaben','Calendar'=>'Kalender','Dashboard'=>'Kennzahlen','Categories'=>'Kategorien','Autocheck'=>'Selbsttest',
            'New task'=>'Neue Aufgabe','Edit task'=>'Aufgabe bearbeiten','Title'=>'Titel','Description'=>'Beschreibung',
            'Category'=>'Kategorie','Priority'=>'Priorität','Status'=>'Status','Assignee'=>'Zuständig',
            'Start date'=>'Startdatum','Due date'=>'Fälligkeit','Completed at'=>'Abgeschlossen am','Save task'=>'Aufgabe speichern',
            'Delete'=>'Löschen','Edit'=>'Bearbeiten','All'=>'Alle','Filter'=>'Filtern','No tasks found.'=>'Keine Aufgaben.',
            'Back'=>'Zurück','Previous'=>'Zurück','Next'=>'Weiter','Save category'=>'Kategorie speichern',
            'Category name'=>'Kategoriename','No category'=>'Ohne Kategorie','Unassigned'=>'Nicht zugewiesen',
            'Low'=>'Niedrig','Normal'=>'Normal','High'=>'Hoch','Urgent'=>'Dringend','Pending'=>'Offen',
            'In progress'=>'In Bearbeitung','Blocked'=>'Blockiert','Completed'=>'Abgeschlossen','Total'=>'Gesamt',
            'Overdue'=>'Überfällig','By status'=>'Nach Status','By priority'=>'Nach Priorität',
            'By assignee'=>'Nach Zuständigkeit','Download JSON'=>'JSON herunterladen',
            'Run autocheck'=>'Selbsttest ausführen','Last executed'=>'Zuletzt ausgeführt',
            'Name'=>'Name','Actions'=>'Aktionen','Task saved.'=>'Aufgabe gespeichert.','Task deleted.'=>'Aufgabe gelöscht.',
            'Category saved.'=>'Kategorie gespeichert.','Category deleted.'=>'Kategorie gelöscht.',
            'Cannot delete a category that has tasks.'=>'Kategorie mit Aufgaben kann nicht gelöscht werden.',
            'Task agenda'=>'Aufgabenplaner','Private task management, calendar and indicators.'=>'Private Aufgabenverwaltung, Kalender und Kennzahlen.',
            'All statuses'=>'Alle Status','All priorities'=>'Alle Prioritäten','All categories'=>'Alle Kategorien',
            'All assignees'=>'Alle Zuständigen','Cancel'=>'Abbrechen',
            'Only registered WordPress users can be assigned.'=>'Nur registrierte WordPress-Benutzer können zugewiesen werden.',
        ),
    );
    // Literal gettext calls support WordPress language packs and bundled MO files.
    $native = array(
        "User manual" => __( "User manual", 'taxocommerce-tasks' ),
        "Technical manual" => __( "Technical manual", 'taxocommerce-tasks' ),
        "New category" => __( "New category", 'taxocommerce-tasks' ),
        "Edit category" => __( "Edit category", 'taxocommerce-tasks' ),
        "Update category" => __( "Update category", 'taxocommerce-tasks' ),
        "Category updated." => __( "Category updated.", 'taxocommerce-tasks' ),
        "Category not found." => __( "Category not found.", 'taxocommerce-tasks' ),
        "Unable to save category. Name must be unique." => __( "Unable to save category. Name must be unique.", 'taxocommerce-tasks' ),
        "Renaming a category preserves the tasks assigned to it." => __( "Renaming a category preserves the tasks assigned to it.", 'taxocommerce-tasks' ),
        "Cancel edit" => __( "Cancel edit", 'taxocommerce-tasks' ),
        "Monday" => __( "Monday", 'taxocommerce-tasks' ),
        "Tuesday" => __( "Tuesday", 'taxocommerce-tasks' ),
        "Wednesday" => __( "Wednesday", 'taxocommerce-tasks' ),
        "Thursday" => __( "Thursday", 'taxocommerce-tasks' ),
        "Friday" => __( "Friday", 'taxocommerce-tasks' ),
        "Saturday" => __( "Saturday", 'taxocommerce-tasks' ),
        "Sunday" => __( "Sunday", 'taxocommerce-tasks' ),
        "Tasks" => __( "Tasks", 'taxocommerce-tasks' ),
        "Calendar" => __( "Calendar", 'taxocommerce-tasks' ),
        "Dashboard" => __( "Dashboard", 'taxocommerce-tasks' ),
        "Categories" => __( "Categories", 'taxocommerce-tasks' ),
        "Autocheck" => __( "Autocheck", 'taxocommerce-tasks' ),
        "New task" => __( "New task", 'taxocommerce-tasks' ),
        "Edit task" => __( "Edit task", 'taxocommerce-tasks' ),
        "Title" => __( "Title", 'taxocommerce-tasks' ),
        "Description" => __( "Description", 'taxocommerce-tasks' ),
        "Category" => __( "Category", 'taxocommerce-tasks' ),
        "Priority" => __( "Priority", 'taxocommerce-tasks' ),
        "Status" => __( "Status", 'taxocommerce-tasks' ),
        "Assignee" => __( "Assignee", 'taxocommerce-tasks' ),
        "Start date" => __( "Start date", 'taxocommerce-tasks' ),
        "Due date" => __( "Due date", 'taxocommerce-tasks' ),
        "Completed at" => __( "Completed at", 'taxocommerce-tasks' ),
        "Save task" => __( "Save task", 'taxocommerce-tasks' ),
        "Delete" => __( "Delete", 'taxocommerce-tasks' ),
        "Edit" => __( "Edit", 'taxocommerce-tasks' ),
        "All" => __( "All", 'taxocommerce-tasks' ),
        "Filter" => __( "Filter", 'taxocommerce-tasks' ),
        "No tasks found." => __( "No tasks found.", 'taxocommerce-tasks' ),
        "Back" => __( "Back", 'taxocommerce-tasks' ),
        "Previous" => __( "Previous", 'taxocommerce-tasks' ),
        "Next" => __( "Next", 'taxocommerce-tasks' ),
        "Save category" => __( "Save category", 'taxocommerce-tasks' ),
        "Category name" => __( "Category name", 'taxocommerce-tasks' ),
        "No category" => __( "No category", 'taxocommerce-tasks' ),
        "Unassigned" => __( "Unassigned", 'taxocommerce-tasks' ),
        "Low" => __( "Low", 'taxocommerce-tasks' ),
        "Normal" => __( "Normal", 'taxocommerce-tasks' ),
        "High" => __( "High", 'taxocommerce-tasks' ),
        "Urgent" => __( "Urgent", 'taxocommerce-tasks' ),
        "Pending" => __( "Pending", 'taxocommerce-tasks' ),
        "In progress" => __( "In progress", 'taxocommerce-tasks' ),
        "Blocked" => __( "Blocked", 'taxocommerce-tasks' ),
        "Completed" => __( "Completed", 'taxocommerce-tasks' ),
        "Total" => __( "Total", 'taxocommerce-tasks' ),
        "Overdue" => __( "Overdue", 'taxocommerce-tasks' ),
        "By status" => __( "By status", 'taxocommerce-tasks' ),
        "By priority" => __( "By priority", 'taxocommerce-tasks' ),
        "By assignee" => __( "By assignee", 'taxocommerce-tasks' ),
        "Download JSON" => __( "Download JSON", 'taxocommerce-tasks' ),
        "Run autocheck" => __( "Run autocheck", 'taxocommerce-tasks' ),
        "Last executed" => __( "Last executed", 'taxocommerce-tasks' ),
        "Name" => __( "Name", 'taxocommerce-tasks' ),
        "Actions" => __( "Actions", 'taxocommerce-tasks' ),
        "Task saved." => __( "Task saved.", 'taxocommerce-tasks' ),
        "Task deleted." => __( "Task deleted.", 'taxocommerce-tasks' ),
        "Category saved." => __( "Category saved.", 'taxocommerce-tasks' ),
        "Category deleted." => __( "Category deleted.", 'taxocommerce-tasks' ),
        "Cannot delete a category that has tasks." => __( "Cannot delete a category that has tasks.", 'taxocommerce-tasks' ),
        "Task agenda" => __( "Task agenda", 'taxocommerce-tasks' ),
        "Private task management, calendar and indicators." => __( "Private task management, calendar and indicators.", 'taxocommerce-tasks' ),
        "All statuses" => __( "All statuses", 'taxocommerce-tasks' ),
        "All priorities" => __( "All priorities", 'taxocommerce-tasks' ),
        "All categories" => __( "All categories", 'taxocommerce-tasks' ),
        "All assignees" => __( "All assignees", 'taxocommerce-tasks' ),
        "Cancel" => __( "Cancel", 'taxocommerce-tasks' ),
        "Only registered WordPress users can be assigned." => __( "Only registered WordPress users can be assigned.", 'taxocommerce-tasks' ),
    );
    if ( isset( $native[ $source ] ) && $native[ $source ] !== $source ) {
        return $native[ $source ];
    }
    return isset( $labels[ $lang ][ $source ] ) ? $labels[ $lang ][ $source ] : $source;
}
// Prefer WordPress language packs, with a bundled base-language fallback for regional locales.
add_action( 'init', static function () {
    $domain = 'taxocommerce-tasks';
    if ( is_textdomain_loaded( $domain ) ) {
        return;
    }
    $locale = sanitize_file_name( determine_locale() );
    $official_pack = WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale;
    if ( is_readable( $official_pack . '.mo' ) || is_readable( $official_pack . '.l10n.php' ) ) {
        return;
    }
    $catalog = __DIR__ . '/languages/' . $domain . '-' . $locale . '.mo';
    if ( ! is_readable( $catalog ) ) {
        $base_language = strtolower( substr( $locale, 0, 2 ) );
        $fallback = array( 'es' => 'es_ES', 'en' => 'en_US', 'fr' => 'fr_FR', 'de' => 'de_DE', 'nl' => 'nl_NL' );
        if ( isset( $fallback[ $base_language ] ) ) {
            $catalog = __DIR__ . '/languages/' . $domain . '-' . $fallback[ $base_language ] . '.mo';
        }
    }
    if ( is_readable( $catalog ) ) {
        load_textdomain( $domain, $catalog );
    }
}, 1 );

function tctasks_e( $source ) {
    echo esc_html( tctasks_label( $source ) );
}
function tctasks_tables() {
    global $wpdb;
    return array( 'tasks' => $wpdb->prefix . 'tctasks_tasks', 'categories' => $wpdb->prefix . 'tctasks_categories' );
}
function tctasks_activate() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $tables  = tctasks_tables();
    $collate = $wpdb->get_charset_collate();
    dbDelta( "CREATE TABLE {$tables['categories']} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
name varchar(190) NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY name (name)
) $collate;" );
    dbDelta( "CREATE TABLE {$tables['tasks']} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
title varchar(255) NOT NULL,
description longtext NOT NULL,
category_id bigint(20) unsigned NOT NULL DEFAULT 0,
priority varchar(20) NOT NULL DEFAULT 'normal',
status varchar(20) NOT NULL DEFAULT 'pending',
assignee_id bigint(20) unsigned NOT NULL DEFAULT 0,
start_date date DEFAULT NULL,
due_date date DEFAULT NULL,
completed_at datetime DEFAULT NULL,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY status (status),
KEY priority (priority),
KEY due_date (due_date),
KEY assignee_id (assignee_id),
KEY category_id (category_id)
) $collate;" );
    update_option( 'tctasks_db_version', TAXOCOMMERCE_TASKS_VERSION, false );
}
register_activation_hook( __FILE__, 'tctasks_activate' );

/**
 * Keep Tasks in the TaxoCommerce family submenu when Core is available.
 *
 * Tasks remains fully independent: without Core, its own top-level menu is
 * registered instead. Both paths preserve the original page slug and callback.
 */
function tctasks_admin_menu() {
    if ( function_exists( 'taxocommerce_core' ) || defined( 'TAXOCOMMERCE_CORE_VERSION' ) ) {
        add_submenu_page(
            'taxocommerce-core',
            tctasks_label( 'Task agenda' ),
            tctasks_label( 'Tasks' ),
            'manage_options',
            'taxocommerce-tasks',
            'tctasks_admin'
        );
        return;
    }

    add_menu_page(
        tctasks_label( 'Task agenda' ),
        tctasks_label( 'Tasks' ),
        'manage_options',
        'taxocommerce-tasks',
        'tctasks_admin',
        'dashicons-clipboard',
        58
    );
}
add_action( 'admin_menu', 'tctasks_admin_menu', 60 );

/**
 * Register the task agenda in the optional Core module registry.
 *
 * @param object $core Optional Core registry.
 * @return void
 */
function tctasks_register_core_module( $core ) {
    if ( ! is_object( $core ) || ! method_exists( $core, 'register_module' ) ) {
        return;
    }

    $core->register_module(
        'tasks',
        array(
            'name'        => 'TaxoCommerce Tasks',
            'version'     => TAXOCOMMERCE_TASKS_VERSION,
            'status'      => 'active',
            'description' => 'Private task agenda, calendar, categories and indicators.',
            'admin_url'   => admin_url( 'admin.php?page=taxocommerce-tasks' ),
        )
    );
}
add_action( 'taxocommerce_core_register_modules', 'tctasks_register_core_module' );

/**
 * Include Tasks in the Core extensions page without depending on Core.
 *
 * @param array $extensions Current catalog entries.
 * @return array Updated catalog entries.
 */
function tctasks_register_family_extension( $extensions ) {
    $extensions = is_array( $extensions ) ? $extensions : array();
    $extensions['tasks'] = array(
        'id'            => 'tasks',
        'name'          => 'TaxoCommerce Tasks',
        'plugin_file'   => 'taxocommerce-tasks/taxocommerce-tasks.php',
        'org_slug'      => 'taxocommerce-tasks',
        'search_term'   => 'TaxoCommerce Tasks',
        'wordpress_org' => false,
        'admin_url'     => admin_url( 'admin.php?page=taxocommerce-tasks' ),
        'description'   => 'Private task agenda, calendar, categories and indicators.',
    );
    return $extensions;
}
add_filter( 'taxocommerce_core_family_extensions', 'tctasks_register_family_extension' );
function tctasks_states() {
    return array( 'pending'=>'Pending', 'progress'=>'In progress', 'blocked'=>'Blocked', 'completed'=>'Completed' );
}
function tctasks_priorities() {
    return array( 'low'=>'Low', 'normal'=>'Normal', 'high'=>'High', 'urgent'=>'Urgent' );
}
function tctasks_date( $value ) {
    if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
        return null;
    }
    $parts = array_map( 'intval', explode( '-', $value ) );
    return checkdate( $parts[1], $parts[2], $parts[0] ) ? $value : null;
}
function tctasks_redirect( $tab, $message = '' ) {
    $args = array( 'page'=>'taxocommerce-tasks', 'tab'=>$tab );
    if ( $message ) {
        $args['notice'] = $message;
    }
    wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
    exit;
}
function tctasks_save_task() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'taxocommerce-tasks' ) );
    }
    check_admin_referer( 'tctasks_save_task' );
    global $wpdb;
    $tables   = tctasks_tables();
    $id       = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
    $title    = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
    $details  = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
    $priority = isset( $_POST['priority'] ) ? sanitize_key( wp_unslash( $_POST['priority'] ) ) : 'normal';
    $status   = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'pending';
    $category = isset( $_POST['category_id'] ) ? absint( wp_unslash( $_POST['category_id'] ) ) : 0;
    $assignee = isset( $_POST['assignee_id'] ) ? absint( wp_unslash( $_POST['assignee_id'] ) ) : 0;
    $start    = isset( $_POST['start_date'] ) ? tctasks_date( sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) ) : null;
    $due      = isset( $_POST['due_date'] ) ? tctasks_date( sanitize_text_field( wp_unslash( $_POST['due_date'] ) ) ) : null;
    if ( '' === $title || ! isset( tctasks_priorities()[ $priority ] ) || ! isset( tctasks_states()[ $status ] ) ) {
        wp_die( esc_html__( 'Invalid task data.', 'taxocommerce-tasks' ) );
    }
    if ( $start && $due && $due < $start ) {
        wp_die( esc_html__( 'Due date cannot be earlier than start date.', 'taxocommerce-tasks' ) );
    }
    if ( $assignee && ! get_user_by( 'id', $assignee ) ) {
        wp_die( esc_html( tctasks_label( 'Only registered WordPress users can be assigned.' ) ) );
    }
    if ( $category ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned categories table.
        $found = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE id = %d', $tables['categories'], $category ) );
        if ( ! $found ) {
            wp_die( esc_html__( 'Invalid category.', 'taxocommerce-tasks' ) );
        }
    }
    $now = current_time( 'mysql' );
    $data = array(
        'title'=>$title, 'description'=>$details, 'category_id'=>$category, 'priority'=>$priority,
        'status'=>$status, 'assignee_id'=>$assignee, 'start_date'=>$start, 'due_date'=>$due,
        'updated_at'=>$now,
    );
    if ( $id ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Private custom table with no WordPress CRUD API.
        $existing = $wpdb->get_row( $wpdb->prepare( 'SELECT status,completed_at FROM %i WHERE id = %d', $tables['tasks'], $id ) );
        if ( ! $existing ) {
            wp_die( esc_html__( 'Task not found.', 'taxocommerce-tasks' ) );
        }
        $data['completed_at'] = 'completed' === $status ? ( 'completed' === $existing->status && $existing->completed_at ? $existing->completed_at : $now ) : null;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned private table.
        $wpdb->update( $tables['tasks'], $data, array( 'id'=>$id ) );
    } else {
        $data['created_at']   = $now;
        $data['completed_at'] = 'completed' === $status ? $now : null;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned private table.
        $wpdb->insert( $tables['tasks'], $data );
    }
    tctasks_redirect( 'tasks', 'saved' );
}
add_action( 'admin_post_tctasks_save_task', 'tctasks_save_task' );
function tctasks_delete_task() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Forbidden', 'taxocommerce-tasks' ) );
    }
    $id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
    check_admin_referer( 'tctasks_delete_task_' . $id );
    global $wpdb;
    $tables = tctasks_tables();
    if ( $id ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned private table.
        $wpdb->delete( $tables['tasks'], array( 'id'=>$id ) );
    }
    tctasks_redirect( 'tasks', 'deleted' );
}
add_action( 'admin_post_tctasks_delete_task', 'tctasks_delete_task' );
/**
 * Insert or rename a private category without changing its ID or its tasks.
 *
 * @param int    $id   Existing category ID, or zero to create.
 * @param string $name Already sanitized category name.
 * @return string A whitelist of notice keys.
 */
function tctasks_upsert_category( $id, $name ) {
    global $wpdb;

    $tables = tctasks_tables();

    if ( $id ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Validate a plugin-owned category before updating.
        $exists = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE id = %d', $tables['categories'], $id ) );
        if ( ! $exists ) {
            return 'category-missing';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No WordPress API for private task categories.
        $result = $wpdb->update(
            $tables['categories'],
            array( 'name' => $name ),
            array( 'id' => $id ),
            array( '%s' ),
            array( '%d' )
        );
        return false === $result ? 'category-error' : 'category-updated';
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Private category table, unique name enforced by database.
    $result = $wpdb->insert( $tables['categories'], array( 'name' => $name ), array( '%s' ) );
    return false === $result ? 'category-error' : 'category-saved';
}

/** Secure POST handler for creating and renaming task categories. */
function tctasks_save_category() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Forbidden', 'taxocommerce-tasks' ) );
    }
    check_admin_referer( 'tctasks_save_category' );
    $id   = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
    $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
    $length = function_exists( 'mb_strlen' ) ? mb_strlen( $name, 'UTF-8' ) : strlen( $name );
    if ( '' === $name || $length > 190 ) {
        wp_die( esc_html__( 'Invalid category name.', 'taxocommerce-tasks' ) );
    }

    tctasks_redirect( 'categories', tctasks_upsert_category( $id, $name ) );
}
add_action( 'admin_post_tctasks_save_category', 'tctasks_save_category' );
function tctasks_delete_category() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Forbidden', 'taxocommerce-tasks' ) );
    }
    $id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
    check_admin_referer( 'tctasks_delete_category_' . $id );
    global $wpdb;
    $tables = tctasks_tables();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live reference check before deletion.
    $count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE category_id = %d', $tables['tasks'], $id ) );
    if ( (int) $count > 0 ) {
        tctasks_redirect( 'categories', 'category-used' );
    }
    if ( $id ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Private categories table.
        $wpdb->delete( $tables['categories'], array( 'id'=>$id ) );
    }
    tctasks_redirect( 'categories', 'category-deleted' );
}
add_action( 'admin_post_tctasks_delete_category', 'tctasks_delete_category' );
function tctasks_categories() {
    global $wpdb;
    $tables = tctasks_tables();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Private small categories inventory.
    return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id,name FROM %i ORDER BY name', $tables['categories'] ) );
}
function tctasks_people() {
    return get_users( array( 'fields'=>array( 'ID', 'display_name' ), 'orderby'=>'display_name' ) );
}
function tctasks_admin() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $allowed = array( 'tasks','calendar','dashboard','categories','autocheck','manual-user','manual-technical' );
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only administrator navigation/filter, no stored data is changed.
    $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'tasks';
    if ( ! in_array( $tab, $allowed, true ) ) {
        $tab = 'tasks';
    }
    $labels = array( 'tasks'=>'Tasks', 'calendar'=>'Calendar', 'dashboard'=>'Dashboard', 'categories'=>'Categories', 'autocheck'=>'Autocheck', 'manual-user'=>'User manual', 'manual-technical'=>'Technical manual' );
    // Title, version and release date are rendered once by admin-branding.php.
    echo '<div class="wrap"><nav class="nav-tab-wrapper">';
    foreach ( $labels as $key=>$label ) {
        $url = add_query_arg( array( 'page'=>'taxocommerce-tasks','tab'=>$key ), admin_url( 'admin.php' ) );
        echo '<a class="nav-tab ' . ( $tab === $key ? 'nav-tab-active' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( tctasks_label( $label ) ) . '</a>';
    }
    echo '</nav>';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only administrator navigation/filter, no stored data is changed.
    $notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';
    $notices = array( 'saved'=>'Task saved.','deleted'=>'Task deleted.','category-saved'=>'Category saved.','category-deleted'=>'Category deleted.','category-used'=>'Cannot delete a category that has tasks.', 'category-updated'=>'Category updated.', 'category-missing'=>'Category not found.', 'category-error'=>'Unable to save category. Name must be unique.' );
    if ( isset( $notices[ $notice ] ) ) {
        $notice_class = in_array( $notice, array( 'category-missing', 'category-error' ), true ) ? 'notice-error' : 'notice-info';
        echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible"><p>' . esc_html( tctasks_label( $notices[ $notice ] ) ) . '</p></div>';
    }
    if ( 'tasks' === $tab ) {
        tctasks_view_tasks();
    } elseif ( 'categories' === $tab ) {
        tctasks_view_categories();
    } elseif ( 'calendar' === $tab ) {
        tctasks_view_calendar();
    } elseif ( 'dashboard' === $tab ) {
        tctasks_view_dashboard();
    } elseif ( 'manual-user' === $tab || 'manual-technical' === $tab ) {
        TCTasks_Manuals::render( 'manual-technical' === $tab ? 'technical' : 'user' );
    } else {
        tctasks_view_autocheck();
    }
    echo '</div>';
}
function tctasks_select( $name, $options, $value, $any = '' ) {
    echo '<select name="' . esc_attr( $name ) . '" id="tctasks-' . esc_attr( $name ) . '">';
    if ( $any ) {
        echo '<option value="">' . esc_html( tctasks_label( $any ) ) . '</option>';
    }
    foreach ( $options as $id=>$label ) {
        echo '<option value="' . esc_attr( (string) $id ) . '" ' . selected( (string) $value, (string) $id, false ) . '>' . esc_html( tctasks_label( $label ) ) . '</option>';
    }
    echo '</select>';
}
function tctasks_view_tasks() {
    global $wpdb;
    $tables = tctasks_tables();
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only administrator navigation/filter, no stored data is changed.
    $edit_id = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Always show current task when editing.
    $editing = $edit_id ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $tables['tasks'], $edit_id ) ) : null;
    $categories = tctasks_categories();
    $people = tctasks_people();
    $cats = array( 0=>'No category' );
    foreach ( $categories as $cat ) { $cats[ (int) $cat->id ] = $cat->name; }
    $users = array( 0=>'Unassigned' );
    foreach ( $people as $person ) { $users[ (int) $person->ID ] = $person->display_name; }
    echo '<h2>' . esc_html( tctasks_label( $editing ? 'Edit task' : 'New task' ) ) . '</h2>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tctasks_save_task"><input type="hidden" name="id" value="' . esc_attr( $editing ? $editing->id : 0 ) . '">';
    wp_nonce_field( 'tctasks_save_task' );
    echo '<table class="form-table" role="presentation"><tbody>';
    $fields = array( 'title'=>'Title', 'description'=>'Description', 'category_id'=>'Category', 'priority'=>'Priority', 'status'=>'Status', 'assignee_id'=>'Assignee', 'start_date'=>'Start date', 'due_date'=>'Due date' );
    foreach ( $fields as $key=>$label ) {
        $value = $editing && isset( $editing->$key ) ? $editing->$key : '';
        if ( 'priority' === $key && ! $value ) { $value = 'normal'; }
        if ( 'status' === $key && ! $value ) { $value = 'pending'; }
        echo '<tr><th scope="row"><label for="tctasks-' . esc_attr( $key ) . '">' . esc_html( tctasks_label( $label ) ) . '</label></th><td>';
        if ( 'description' === $key ) {
            echo '<textarea rows="3" class="large-text" id="tctasks-description" name="description">' . esc_textarea( $value ) . '</textarea>';
        } elseif ( 'category_id' === $key ) {
            tctasks_select( $key, $cats, $value );
        } elseif ( 'assignee_id' === $key ) {
            tctasks_select( $key, $users, $value );
        } elseif ( 'priority' === $key ) {
            tctasks_select( $key, tctasks_priorities(), $value );
        } elseif ( 'status' === $key ) {
            tctasks_select( $key, tctasks_states(), $value );
        } else {
            $type = in_array( $key, array('start_date','due_date'), true ) ? 'date' : 'text';
            echo '<input id="tctasks-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" type="' . esc_attr( $type ) . '" value="' . esc_attr( $value ) . '" ' . ( 'title' === $key ? 'required maxlength="255" class="regular-text"' : '' ) . '>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table>';
    submit_button( tctasks_label( 'Save task' ) );
    echo '</form><hr><h2>' . esc_html( tctasks_label( 'Tasks' ) ) . '</h2>';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only administrator navigation/filter, no stored data is changed.
    $f_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only administrator navigation/filter, no stored data is changed.
    $f_priority = isset( $_GET['priority'] ) ? sanitize_key( wp_unslash( $_GET['priority'] ) ) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only administrator navigation/filter, no stored data is changed.
    $f_category = isset( $_GET['category'] ) ? absint( wp_unslash( $_GET['category'] ) ) : 0;
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only administrator navigation/filter, no stored data is changed.
    $f_assignee = isset( $_GET['assignee'] ) ? absint( wp_unslash( $_GET['assignee'] ) ) : 0;
    echo '<form method="get"><input type="hidden" name="page" value="taxocommerce-tasks"><input type="hidden" name="tab" value="tasks">';
    tctasks_select( 'status', tctasks_states(), $f_status, 'All statuses' );
    tctasks_select( 'priority', tctasks_priorities(), $f_priority, 'All priorities' );
    tctasks_select( 'category', $cats, $f_category, 'All categories' );
    tctasks_select( 'assignee', $users, $f_assignee, 'All assignees' );
    echo ' <button class="button">' . esc_html( tctasks_label( 'Filter' ) ) . '</button></form>';
    // Read-only query with a fixed SQL template and fully prepared values.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Private plugin task table.
    $tasks = (array) $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM %i
         WHERE (%s = '' OR status = %s)
           AND (%s = '' OR priority = %s)
           AND (%d = 0 OR category_id = %d)
           AND (%d = 0 OR assignee_id = %d)
         ORDER BY FIELD(status, 'pending', 'progress', 'blocked', 'completed'),
                  FIELD(priority, 'urgent', 'high', 'normal', 'low'),
                  due_date IS NULL, due_date ASC, id DESC LIMIT 500",
        $tables['tasks'], $f_status, $f_status, $f_priority, $f_priority,
        $f_category, $f_category, $f_assignee, $f_assignee
    ) );
    echo '<table class="widefat striped"><thead><tr>';
    foreach ( array('Title','Status','Priority','Category','Assignee','Due date','Actions') as $h ) { echo '<th scope="col">' . esc_html( tctasks_label( $h ) ) . '</th>'; }
    echo '</tr></thead><tbody>';
    if ( ! $tasks ) { echo '<tr><td colspan="7">' . esc_html( tctasks_label( 'No tasks found.' ) ) . '</td></tr>'; }
    foreach ( $tasks as $task ) {
        $edit = add_query_arg( array('page'=>'taxocommerce-tasks','tab'=>'tasks','edit'=>$task->id), admin_url('admin.php') );
        echo '<tr><td><strong>' . esc_html( $task->title ) . '</strong></td><td>' . esc_html( tctasks_label( tctasks_states()[ $task->status ] ?? $task->status ) ) . '</td><td>' . esc_html( tctasks_label( tctasks_priorities()[ $task->priority ] ?? $task->priority ) ) . '</td><td>' . esc_html( tctasks_label( $cats[ (int) $task->category_id ] ?? 'No category' ) ) . '</td><td>' . esc_html( $users[ (int) $task->assignee_id ] ?? tctasks_label('Unassigned') ) . '</td><td>' . esc_html( $task->due_date ?: '—' ) . '</td><td><a href="' . esc_url($edit) . '">' . esc_html(tctasks_label('Edit')) . '</a> ';
        echo '<form method="post" style="display:inline" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="tctasks_delete_task"><input type="hidden" name="id" value="' . esc_attr($task->id) . '">';
        wp_nonce_field('tctasks_delete_task_' . $task->id);
        echo '<button class="button-link-delete" type="submit">' . esc_html(tctasks_label('Delete')) . '</button></form></td></tr>';
    }
    echo '</tbody></table>';
}
/** Render the private category editor and the list of existing categories. */
function tctasks_view_categories() {
    $categories = tctasks_categories();

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This GET parameter only selects a prefilled read-only admin form.
    $edit_id = isset( $_GET['edit_category'] ) ? absint( wp_unslash( $_GET['edit_category'] ) ) : 0;
    $editing = null;
    foreach ( $categories as $category ) {
        if ( (int) $category->id === $edit_id ) {
            $editing = $category;
            break;
        }
    }

    echo '<h2>' . esc_html( tctasks_label( $editing ? 'Edit category' : 'New category' ) ) . '</h2>';
    echo '<p>' . esc_html( tctasks_label( 'Renaming a category preserves the tasks assigned to it.' ) ) . '</p>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="tctasks_save_category">';
    echo '<input type="hidden" name="id" value="' . esc_attr( $editing ? (string) $editing->id : '0' ) . '">';
    wp_nonce_field( 'tctasks_save_category' );
    echo '<label for="tctasks-category-name">' . esc_html( tctasks_label( 'Category name' ) ) . '</label> ';
    echo '<input id="tctasks-category-name" name="name" type="text" class="regular-text" required maxlength="190" value="' . esc_attr( $editing ? $editing->name : '' ) . '"> ';
    submit_button( tctasks_label( $editing ? 'Update category' : 'Save category' ), 'secondary', 'submit', false );
    if ( $editing ) {
        $cancel = add_query_arg( array( 'page' => 'taxocommerce-tasks', 'tab' => 'categories' ), admin_url( 'admin.php' ) );
        echo ' <a class="button" href="' . esc_url( $cancel ) . '">' . esc_html( tctasks_label( 'Cancel edit' ) ) . '</a>';
    }
    echo '</form>';

    echo '<h2>' . esc_html( tctasks_label( 'Categories' ) ) . '</h2>';
    echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html( tctasks_label( 'Name' ) ) . '</th><th scope="col">' . esc_html( tctasks_label( 'Actions' ) ) . '</th></tr></thead><tbody>';
    foreach ( $categories as $cat ) {
        $url = add_query_arg( array( 'page' => 'taxocommerce-tasks', 'tab' => 'categories', 'edit_category' => (int) $cat->id ), admin_url( 'admin.php' ) );
        echo '<tr><td>' . esc_html( $cat->name ) . '</td><td>';
        echo '<a href="' . esc_url( $url ) . '">' . esc_html( tctasks_label( 'Edit' ) ) . '</a> ';
        echo '<form method="post" style="display:inline" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="tctasks_delete_category"><input type="hidden" name="id" value="' . esc_attr( (string) $cat->id ) . '">';
        wp_nonce_field( 'tctasks_delete_category_' . $cat->id );
        echo '<button type="submit" class="button-link-delete">' . esc_html( tctasks_label( 'Delete' ) ) . '</button></form>';
        echo '</td></tr>';
    }
    echo '</tbody></table>';
}
/** Render the independent monthly duration calendar. */
function tctasks_view_calendar() {
    TCTasks_Calendar::render();
}
function tctasks_counts() {
    global $wpdb;
    $tables=tctasks_tables();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Current aggregation from private custom table.
    $rows=(array)$wpdb->get_results($wpdb->prepare('SELECT status,priority,assignee_id,due_date FROM %i', $tables['tasks']));
    $result=array('total'=>count($rows),'overdue'=>0,'status'=>array_fill_keys(array_keys(tctasks_states()),0),'priority'=>array_fill_keys(array_keys(tctasks_priorities()),0),'assignee'=>array());
    $today=wp_date('Y-m-d');
    foreach($rows as $row) {
        if(isset($result['status'][$row->status])) { $result['status'][$row->status]++; }
        if(isset($result['priority'][$row->priority])) { $result['priority'][$row->priority]++; }
        if($row->due_date && $row->due_date<$today && 'completed'!==$row->status) { $result['overdue']++; }
        $user=(int)$row->assignee_id;
        $result['assignee'][$user]=($result['assignee'][$user]??0)+1;
    }
    return $result;
}
function tctasks_view_dashboard() {
    $data=tctasks_counts();
    echo '<h2>' . esc_html(tctasks_label('Dashboard')) . '</h2><p><strong>' . esc_html(tctasks_label('Total')) . ': ' . esc_html((string)$data['total']) . '</strong> &nbsp; | &nbsp; <strong>' . esc_html(tctasks_label('Overdue')) . ': ' . esc_html((string)$data['overdue']) . '</strong></p>';
    $groups=array('By status'=>$data['status'],'By priority'=>$data['priority'],'By assignee'=>$data['assignee']);
    foreach($groups as $heading=>$items) {
        echo '<h3>' . esc_html(tctasks_label($heading)) . '</h3><div style="max-width:680px">';
        foreach($items as $key=>$n) {
            if ('By status'===$heading) { $label=tctasks_label(tctasks_states()[$key]??$key); }
            elseif('By priority'===$heading) { $label=tctasks_label(tctasks_priorities()[$key]??$key); }
            else { $person=$key ? get_user_by('id',(int)$key) : false; $label=$person ? $person->display_name : tctasks_label('Unassigned'); }
            $width=$data['total']>0 ? round(100*$n/$data['total'],1) : 0;
            echo '<div style="margin:9px 0"><div>' . esc_html($label) . ': <strong>' . esc_html((string)$n) . '</strong></div><div role="img" aria-label="' . esc_attr($label . ': ' . $n) . '" style="height:18px;background:#e1e1e1"><div style="height:18px;background:#2271b1;width:' . esc_attr((string)$width) . '%"></div></div></div>';
        }
        echo '</div>';
    }
}
function tctasks_autocheck_report() {
    global $wpdb;
    $tables = tctasks_tables();
    $rows = array();
    foreach($tables as $key=>$table) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only check of plugin-owned schema.
        $exists = (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
        $rows[]=array('id'=>'table_'.$key,'status'=>$exists?'pass':'fail','detail'=>$table);
    }
    $rows[]=array('id'=>'capability','status'=>current_user_can('manage_options')?'pass':'fail','detail'=>'manage_options');
    $totals=array('pass'=>0,'fail'=>0);
    foreach($rows as $row) { $totals[$row['status']]++; }
    $generated_at = gmdate( 'c' );
    return array('plugin'=>'taxocommerce-tasks','version'=>TAXOCOMMERCE_TASKS_VERSION,'generated_at'=>$generated_at,'executed_at' => $generated_at,'rows' => $rows,'totals' => $totals);
}
function tctasks_autocheck_run() {
    if(!current_user_can('manage_options')) { wp_die(esc_html__('Forbidden','taxocommerce-tasks')); }
    check_admin_referer('tctasks_autocheck_run');
    update_option('tctasks_autocheck_last', tctasks_autocheck_report(), false);
    tctasks_redirect('autocheck');
}
add_action('admin_post_tctasks_autocheck_run','tctasks_autocheck_run');
function tctasks_autocheck_download() {
    if(!current_user_can('manage_options')) { wp_die(esc_html__('Forbidden','taxocommerce-tasks')); }
    check_admin_referer('tctasks_autocheck_download');
    $report=get_option('tctasks_autocheck_last');
    if(!is_array($report)) { wp_die(esc_html__('No report available.','taxocommerce-tasks')); }
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="taxocommerce-tasks-autocheck.json"');
    echo wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
add_action('admin_post_tctasks_autocheck_download','tctasks_autocheck_download');
function tctasks_view_autocheck() {
    if ( \TaxoCommerce_Tasks_Autotest_Code::render_subtabs() ) { return; }
    $report=get_option('tctasks_autocheck_last');
    echo '<h2>' . esc_html(tctasks_label('Autocheck')) . '</h2>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="tctasks_autocheck_run">';
    wp_nonce_field('tctasks_autocheck_run');
    submit_button(tctasks_label('Run autocheck'),'primary','submit',false);
    echo '</form>';
    if(is_array($report)) {
        echo '<p>' . esc_html(tctasks_label('Last executed')) . ' (Fecha de ejecución: ' . esc_html($report['executed_at']??'') . ')</p>';
        echo '<p>' . esc_html('PASS ' . ($report['totals']['pass']??0) . ' / FAIL ' . ($report['totals']['fail']??0)) . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="tctasks_autocheck_download">';
        wp_nonce_field('tctasks_autocheck_download');
        submit_button(tctasks_label('Download JSON'),'secondary','submit',false);
        echo '</form>';
    }
}
