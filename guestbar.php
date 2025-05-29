<?php
/*
Plugin Name: WP Guest Bar
Plugin URI:   https://wordpress.org/plugins/wp-guest-bar
Description: Adds a BuddyPress guest bar (login+register) to your WordPress site and show a message!
Version: 3.0
Author: Marco Milesi
Author URI:   https://wordpress.org/plugins/wp-guest-bar
Contributors: Milmor
*/
class WpGuestBar {

    function __construct() {
        add_action( 'init', array( $this, 'show_admin_bar' ) );
        add_action( 'admin_init', array( $this, 'register_setting' ) );
        add_action( 'admin_bar_menu', array( $this, 'customize_admin_bar' ), 11 );
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_script' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) ); // For media uploader
    }

    function customize_admin_bar( $wp_admin_bar ) {
        if ( is_user_logged_in() ) {
            return;
        }

        $options = get_option( 'wpgov_wpgb' );

        // Custom Logo/Icon
        $logo_html = '';
        if ( !empty( $options['logo'] ) ) {
            $logo_html = '<img src="' . esc_url( $options['logo'] ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" style="height:20px;vertical-align:middle;margin-right:5px;" />';
        } else {
            $logo_html = '<span class="ab-icon dashicons dashicons-admin-home"></span>';
        }

        $wp_admin_bar->remove_node( 'wp-logo' );

        $wp_admin_bar->add_menu( array(
            'id'     => 'wpgb-home',
            'parent' => null,
            'group'  => null,
            'title'  => $logo_html . '<span class="wpdb-hide-mobile">' . esc_html( get_bloginfo( 'name' ) ) . '</span>',
            'href'   => esc_url( get_site_url() )
        ) );

        if ( get_option( 'users_can_register' ) ) {
            $wp_admin_bar->add_menu(
                array(
                    'id'     => 'wpgb-register',
                    'title'  => esc_html__( 'Register' ),
                    'href'   => esc_url( wp_registration_url() ),
                    'parent' => 'top-secondary'
                )
            );
        }

        $wp_admin_bar->add_menu(
            array(
                'id'     => 'wpgb-login',
                'title'  => '<span class="ab-icon dashicons dashicons-lock"></span><span class="wpdb-hide-mobile">' . esc_html__( 'Log In' ) . '</span>',
                'href'   => esc_url( wp_login_url() ),
                'parent' => 'top-secondary'
            )
        );

        // Custom Links
        if ( !empty( $options['custom_links'] ) ) {
            $links = explode( "\n", $options['custom_links'] );
            $i = 0;
            foreach ( $links as $link ) {
                $parts = explode( '|', trim( $link ), 2 );
                if ( count( $parts ) === 2 ) {
                    $wp_admin_bar->add_menu( array(
                        'id'     => 'wpgb-custom-link-' . $i,
                        'title'  => esc_html( $parts[0] ),
                        'href'   => esc_url( $parts[1] ),
                        'parent' => null,
                        'meta'   => array( 'class' => 'wpgb-custom-link' ),
                    ) );
                    $i++;
                }
            }
        }

        if ( isset( $options['message'] ) && $options['message'] ) {
            $wp_admin_bar->add_menu(
                array(
                    'id'    => 'wpgb-custom-message',
                    'title' => wp_kses_post( $options['message'] )
                )
            );
        }
    }

    function enqueue_script() {
        $admin_css = '
        @media screen and (max-width: 782px) {
            .wpdb-hide-mobile {
                display: none;
            }
            #wpadminbar li#wp-admin-bar-wpgb-home,
            #wpadminbar li#wp-admin-bar-wpgb-login {
                display: block;
            }

            #wpadminbar li#wp-admin-bar-wpgb-home a,
            #wpadminbar li#wp-admin-bar-wpgb-login a {
                padding: 2px 8px;
            }
        }';

        wp_add_inline_style( 'admin-bar', $admin_css );
    }

    function show_admin_bar( $show ) {
        add_filter( 'show_admin_bar', '__return_true', 1000 );
    }

    function register_setting() {
        register_setting(
            'wpgov_wpgb_options',
            'wpgov_wpgb',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_options' ),
                'default'           => array(),
            )
        );
    }

    function enqueue_admin_scripts( $hook ) {
        if ( $hook !== 'settings_page_wpgov_wpgb' ) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script(
            'wpgb-admin-js',
            '', // No external file, inline below
            array( 'jquery' ),
            false,
            true
        );
        add_action( 'admin_footer', function() {
            ?>
            <script>
            jQuery(document).ready(function($){
                var custom_uploader;
                $('#wpgb_logo_upload').on('click', function(e) {
                    e.preventDefault();
                    if (custom_uploader) {
                        custom_uploader.open();
                        return;
                    }
                    custom_uploader = wp.media({
                        title: 'Select Logo',
                        button: { text: 'Use this logo' },
                        multiple: false
                    });
                    custom_uploader.on('select', function() {
                        var attachment = custom_uploader.state().get('selection').first().toJSON();
                        $('#wpgov_wpgb_logo').val(attachment.url);
                        $('#wpgb_logo_preview').attr('src', attachment.url).show();
                    });
                    custom_uploader.open();
                });
            });
            </script>
            <?php
        });
    }

    function settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.' ) );
        }
        $options = get_option( 'wpgov_wpgb' );
        // Prepare custom links as array
        $custom_links = array();
        if ( !empty( $options['custom_links'] ) ) {
            $lines = explode( "\n", $options['custom_links'] );
            foreach ( $lines as $line ) {
                $parts = explode( '|', trim( $line ), 2 );
                if ( count( $parts ) === 2 ) {
                    $custom_links[] = array(
                        'label' => $parts[0],
                        'url'   => $parts[1],
                    );
                }
            }
        }
        ?>
        <div class="wrap">
            <h2>WP Guest Bar</h2>
            <p><?php esc_html_e( 'The bar is only visible to non-logged users.' ); ?></p>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'wpgov_wpgb_options' );
                do_settings_sections( 'wpgov_wpgb_options' );
                wp_nonce_field( 'wpgb_save_settings', 'wpgb_nonce' );
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <label for="wpgov_wpgb_message"><?php esc_html_e( 'Top Bar Message' ); ?></label>
                        </th>
                        <td>
                            <input id="wpgov_wpgb_message" type="text" name="wpgov_wpgb[message]" value="<?php echo esc_attr( isset( $options['message'] ) ? $options['message'] : '' ); ?>" size="80" />
                            <br>
                            <small>
                                <?php esc_html_e( 'You can use HTML. Example:' ); ?>
                                <code>&lt;span style="background-color:red;color:white;padding:5px;"&gt;Hi User :)&lt;/span&gt;</code>
                            </small>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for="wpgov_wpgb_logo"><?php esc_html_e( 'Custom Logo/Icon URL' ); ?></label>
                        </th>
                        <td>
                            <input id="wpgov_wpgb_logo" type="text" name="wpgov_wpgb[logo]" value="<?php echo esc_attr( isset( $options['logo'] ) ? $options['logo'] : '' ); ?>" size="60" />
                            <button id="wpgb_logo_upload" class="button"><?php esc_html_e( 'Upload/Select Image' ); ?></button>
                            <br>
                            <img id="wpgb_logo_preview" src="<?php echo esc_url( isset( $options['logo'] ) ? $options['logo'] : '' ); ?>" style="max-height:40px;<?php echo empty( $options['logo'] ) ? 'display:none;' : ''; ?>" alt="" />
                            <br>
                            <small><?php esc_html_e( 'Upload or paste the URL of an image to use as the guest bar logo.' ); ?></small>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <?php esc_html_e( 'Custom Links' ); ?>
                        </th>
                        <td>
                            <div id="wpgb-links-repeater">
                                <?php if ( !empty( $custom_links ) ) : ?>
                                    <?php foreach ( $custom_links as $i => $link ) : ?>
                                        <div class="wpgb-link-row">
                                            <input type="text" name="wpgov_wpgb[custom_links_label][]" value="<?php echo esc_attr( $link['label'] ); ?>" placeholder="Label" />
                                            <input type="url" name="wpgov_wpgb[custom_links_url][]" value="<?php echo esc_attr( $link['url'] ); ?>" placeholder="https://example.com" style="width:300px;" />
                                            <button type="button" class="button wpgb-remove-link">×</button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <div class="wpgb-link-row">
                                        <input type="text" name="wpgov_wpgb[custom_links_label][]" placeholder="Label" />
                                        <input type="url" name="wpgov_wpgb[custom_links_url][]" placeholder="https://example.com" style="width:300px;" />
                                        <button type="button" class="button wpgb-remove-link">×</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="button" id="wpgb-add-link"><?php esc_html_e( 'Add Link' ); ?></button>
                            <br>
                            <small><?php esc_html_e( 'Add custom links for the guest bar.' ); ?></small>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes' ); ?>" />
                </p>
            </form>
        </div>
        <style>
            .wpgb-link-row { margin-bottom: 6px; }
            .wpgb-link-row input[type="text"] { width: 180px; }
            .wpgb-link-row input[type="url"] { width: 300px; }
            .wpgb-remove-link { margin-left: 5px; }
        </style>
        <script>
        jQuery(document).ready(function($){
            $('#wpgb-add-link').on('click', function(){
                $('#wpgb-links-repeater').append(
                    '<div class="wpgb-link-row">' +
                    '<input type="text" name="wpgov_wpgb[custom_links_label][]" placeholder="Label" /> ' +
                    '<input type="url" name="wpgov_wpgb[custom_links_url][]" placeholder="https://example.com" style="width:300px;" /> ' +
                    '<button type="button" class="button wpgb-remove-link">&times;</button>' +
                    '</div>'
                );
            });
            $(document).on('click', '.wpgb-remove-link', function(){
                $(this).closest('.wpgb-link-row').remove();
            });
        });
        </script>
        <?php
    }

    function sanitize_options( $input ) {
        $output = array();
        if ( isset( $input['message'] ) ) {
            $output['message'] = wp_kses_post( $input['message'] );
        }
        if ( isset( $input['logo'] ) ) {
            $output['logo'] = esc_url_raw( $input['logo'] );
        }
        // Handle repeater fields for custom links
        if ( isset( $input['custom_links_label'], $input['custom_links_url'] ) ) {
            $clean_links = array();
            $labels = $input['custom_links_label'];
            $urls   = $input['custom_links_url'];
            for ( $i = 0; $i < count( $labels ); $i++ ) {
                $label = sanitize_text_field( $labels[$i] );
                $url   = esc_url_raw( $urls[$i] );
                if ( $label && $url ) {
                    $clean_links[] = $label . '|' . $url;
                }
            }
            $output['custom_links'] = implode( "\n", $clean_links );
        }
        return $output;
    }

    function register_menu() {
        add_options_page(
            'WP Guest Bar',
            'WP Guest Bar',
            'manage_options',
            'wpgov_wpgb',
            array( $this, 'settings_page' )
        );
    }
}

$WpGuestBar = new WpGuestBar();
