<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Добавляем страницу настроек
function auto_rename_settings_page() {
    add_options_page(
        'Auto Rename Settings',
        'Auto Rename',
        'manage_options',
        'auto-rename-settings',
        'auto_rename_settings_page_html'
    );
}
add_action( 'admin_menu', 'auto_rename_settings_page' );

// Отображаем HTML для страницы настроек
function auto_rename_settings_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( isset( $_GET['settings-updated'] ) ) {
        add_settings_error( 'auto_rename_messages', 'auto_rename_message', 'Settings Saved', 'updated' );
    }

    settings_errors( 'auto_rename_messages' );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Auto Rename Settings', 'auto-rename' ); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'auto_rename' );
            do_settings_sections( 'auto_rename' );
            submit_button( 'Save Settings' );
            ?>
        </form>
    </div>
    <?php
}

// Регистрируем настройки с улучшенным интерфейсом
function auto_rename_settings_init() {
    register_setting( 'auto_rename', 'auto_rename_options' );

    add_settings_section(
        'auto_rename_section',
        __( 'General Settings', 'auto-rename' ),
        'auto_rename_section_callback',
        'auto_rename'
    );

    add_settings_field(
        'enable_thumbnail_rename',
        __( 'Enable Thumbnail Rename', 'auto-rename' ),
        'auto_rename_enable_thumbnail_callback',
        'auto_rename',
        'auto_rename_section'
    );

    add_settings_field(
        'max_execution_time',
        __( 'Max Execution Time', 'auto-rename' ),
        'auto_rename_execution_time_callback',
        'auto_rename',
        'auto_rename_section'
    );
}

add_action( 'admin_init', 'auto_rename_settings_init' );

// Поле для установки максимального времени выполнения
function auto_rename_execution_time_callback() {
    $options = get_option( 'auto_rename_options' );
    ?>
    <input type="number" name="auto_rename_options[max_execution_time]" value="<?php echo isset( $options['max_execution_time'] ) ? esc_attr( $options['max_execution_time'] ) : '60'; ?>" />
    <p class="description"><?php _e( 'Specify the maximum execution time in seconds for renaming operations.', 'auto-rename' ); ?></p>
    <?php
}

// Callbacks для отображения полей настроек
function auto_rename_section_callback() {
    echo '<p>' . __( 'Manage settings for Auto Rename Plugin.', 'auto-rename' ) . '</p>';
}

function auto_rename_enable_thumbnail_callback() {
    $options = get_option( 'auto_rename_options' );
    ?>
    <input type="checkbox" name="auto_rename_options[enable_thumbnail_rename]" value="1" <?php checked( 1, isset( $options['enable_thumbnail_rename'] ) ? $options['enable_thumbnail_rename'] : 0 ); ?> />
    <?php
}
