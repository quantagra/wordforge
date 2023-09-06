<?php

namespace Bricksforge;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend Pages Handler
 */
class Frontend
{

    private $load_instances = true;
    private $load_timelines = true;

    public function __construct()
    {

        add_action('wp', [$this, 'render_conditionals']);

        wp_enqueue_style('bricksforge-style');
        wp_enqueue_style('bricksforge-style');

        if (bricks_is_builder()) {
            wp_enqueue_style('bricksforge-builder');
            wp_enqueue_script('bricksforge-builder');

            if (!bricks_is_builder_iframe()) {
                //wp_enqueue_script('bricksforge-builder-scripts');
            }
        }

        add_shortcode('bricksforge', [$this, 'render_frontend']);
    }

    public function render_conditionals()
    {
        // Panel
        if (get_option('brf_activated_tools') && in_array(6, get_option('brf_activated_tools'))) {
            $panel_data = get_option('brf_panel');

            if (bricks_is_builder()) {
                wp_enqueue_script('bricksforge-panel');
                wp_enqueue_script('bricksforge-gsap-draggable');
                wp_enqueue_script('bricksforge-gsap-splittext');
                wp_enqueue_script('bricksforge-gsap-flip');
                wp_enqueue_script('bricksforge-gsap-drawsvg');
            }

            if ($panel_data) {
                $panel_data = $panel_data[0];

                $instances = $panel_data->instances ?? false;
                $timelines = $panel_data->timelines ?? false;

                if ($timelines) {
                    $has_enabled_timelines = false;
                    foreach ($timelines as $timeline) {
                        if (isset($timeline->disabled) && $timeline->disabled === false) {
                            $has_enabled_timelines = true;
                            break;
                        }
                    }

                    if ($has_enabled_timelines) {
                        $load_timelines = [];

                        foreach ($timelines as $timeline) {

                            // Check if it needs to be loaded on this page
                            $timeline_needs_loading_check = isset($timeline->loadOnChoice) && $timeline->loadOnChoice == 'specificPages';
                            $timeline_load_on = isset($timeline->loadOn) ? $timeline->loadOn : '';

                            if ($timeline_needs_loading_check && $timeline_load_on == '') {
                                $load_timelines[] = false;
                                continue;
                            }

                            $timeline_post_ids = explode(',', $timeline_load_on);

                            if (is_array($timeline_load_on)) {
                                $timeline_post_ids = array_map('trim', $timeline_load_on);
                            }

                            $timeline_post_ids = array_map(function ($id) {
                                return intval($id);
                            }, $timeline_post_ids);

                            if ($timeline_needs_loading_check == true && !in_array(get_the_ID(), $timeline_post_ids)) {
                                $load_timelines[] = false;
                                continue;
                            } else {
                                $load_timelines[] = true;
                            }
                        }

                        if (!in_array(true, $load_timelines)) {
                            $this->load_timelines = false;
                        }

                        if ($this->load_timelines === true) {
                            wp_enqueue_script('bricksforge-panel');
                            wp_enqueue_script('bricksforge-gsap');

                            $has_scrollTrigger = array_search('scrollTrigger', array_column($timelines, 'trigger')) !== false;
                            $has_drawSVG = strpos(json_encode($timelines), 'drawSVG') !== false;

                            foreach ($timelines as $timeline) {
                                $has_splitText = array_search('true', array_column($timeline->animations, 'splitText')) !== false;
                                if ($has_splitText) {
                                    break;
                                }
                            }

                            if ($has_scrollTrigger) wp_enqueue_script('bricksforge-gsap-scrolltrigger');
                            if ($has_splitText) wp_enqueue_script('bricksforge-gsap-splittext');
                            if ($has_drawSVG) wp_enqueue_script('bricksforge-gsap-drawsvg');
                        }
                    }
                }

                if ($instances) {

                    $has_gsapFlip = $has_gsapSet = $has_gsapTo = $has_draw_svg = $has_gsap = false;

                    $load_instances = [];

                    foreach ($instances as $instance) {
                        if (isset($instance->disabled) && $instance->disabled) continue;

                        // Check if it needs to be loaded on this page
                        $instance_needs_loading_check = isset($instance->loadOnChoice) && $instance->loadOnChoice == 'specificPages';
                        $instance_load_on = isset($instance->loadOn) ? $instance->loadOn : '';

                        if ($instance_needs_loading_check && $instance_load_on == '') {
                            $load_instances[] = false;
                            continue;
                        }

                        $instance_post_ids = explode(',', $instance_load_on);

                        if (is_array($instance_load_on)) {
                            $instance_post_ids = array_map('trim', $instance_post_ids);
                        }

                        $instance_post_ids = array_map(function ($id) {
                            return intval($id);
                        }, $instance_post_ids);

                        if ($instance_needs_loading_check == true && !in_array(get_the_ID(), $instance_post_ids)) {
                            $load_instances[] = false;
                            continue;
                        } else {
                            $load_instances[] = true;
                        }

                        foreach ($instance->actions as $action) {
                            $has_gsapFlip = $has_gsapFlip || (isset($action->action->value) && $action->action->value == 'gsapFlip');
                            $has_gsapSet = $has_gsapSet || (isset($action->action->value) && $action->action->value == 'gsapSet');
                            $has_gsapTo = $has_gsapTo || (isset($action->action->value) && $action->action->value == 'gsapTo');
                            $has_draw_svg = $has_draw_svg || (isset($action->action->gsapSetObject) && strpos($action->action->gsapSetObject, 'drawSVG') !== false);
                            $has_gsap = $has_gsap || (isset($action->action->value) && $action->action->value == 'gsap');
                        }
                    }

                    // If $load_instances contains at least one true value, we set the load_instances flag to true
                    if (!in_array(true, $load_instances)) {
                        $this->load_instances = false;
                    }

                    if ($this->load_instances === true) {

                        wp_enqueue_script('bricksforge-panel');

                        if ($has_gsapSet || $has_gsap || $has_gsapTo) {
                            wp_enqueue_script('bricksforge-gsap');

                            if ($has_draw_svg) {
                                wp_enqueue_script('bricksforge-gsap-drawsvg');
                            }
                        }

                        if ($has_gsapFlip) {
                            wp_enqueue_script('bricksforge-gsap-flip');
                        }
                    }
                }
            }
        }

        if (get_option('brf_activated_tools') && in_array(1, get_option('brf_activated_tools'))) {
            add_action('wp_enqueue_scripts', function () {
                wp_localize_script(
                    'bricksforge-animator',
                    'BRFANIMATIONS',
                    array(
                        'nonce'             => wp_create_nonce('wp_rest'),
                        'siteurl'           => get_option('siteurl'),
                        'pluginurl'         => BRICKSFORGE_URL,
                        'apiurl'            => get_rest_url() . "bricksforge/v1/",
                        'bricksPrefix'      => BRICKSFORGE_BRICKS_ELEMENT_PREFIX,
                    )
                );
            });
        }

        if (get_option('brf_activated_tools') && in_array(5, get_option('brf_activated_tools')) && get_option('brf_popups') && count(get_option('brf_popups')) > 0) {
            wp_enqueue_script('bricksforge-popups');
            add_action('wp_enqueue_scripts', function () {
                wp_localize_script(
                    'bricksforge-popups',
                    'BRFPOPUPS',
                    array(
                        'nonce'       => wp_create_nonce('wp_rest'),
                        'popups'      => get_option('brf_popups'),
                        'apiurl'      => get_rest_url() . "bricksforge/v1/",
                        'currentPage' => get_the_ID()
                    )
                );
            });
        }

        // Scroll Smoother

        if (get_option('brf_activated_tools') && in_array(7, get_option('brf_activated_tools'))) {

            $scrollsmooth_provider = 'gsap';

            $scrollsmooth_settings = get_option('brf_tool_settings');

            if ($scrollsmooth_settings) {
                // Get the scrollsmooth settings with the key id equal to 7
                $scrollsmooth_settings = array_filter($scrollsmooth_settings, function ($setting) {
                    return $setting->id == 7;
                });

                if ($scrollsmooth_settings) {
                    $scrollsmooth_settings = $scrollsmooth_settings[0];
                    $scrollsmooth_provider = isset($scrollsmooth_settings->settings->provider) ? $scrollsmooth_settings->settings->provider : 'gsap';
                }
            }

            if (!$scrollsmooth_provider) {
                $scrollsmooth_provider = 'gsap';
            }

            switch ($scrollsmooth_provider) {
                case 'gsap':
                    wp_enqueue_script('bricksforge-gsap-scrollsmoother');

                    // Wrap needed container IDs
                    add_action('bricks_before_site_wrapper', function () {
                        echo '<div id="smooth-wrapper">';
                        echo '<div id="smooth-content">';
                    });
                    add_action('bricks_after_site_wrapper', function () {
                        echo '</div>';
                        echo '</div>';
                    });
                    break;
                case 'lenis':
                    wp_enqueue_script('bricksforge-lenis');
                    break;
                default:
                    break;
            }

            wp_enqueue_script('bricksforge-scrollsmoother');
            add_action('wp_enqueue_scripts', function () {
                $args = array(
                    'toolSettings' => get_option('brf_tool_settings')
                );

                wp_localize_script('bricksforge-scrollsmoother', 'BRFSCROLLSMOOTHER', $args);
            });
        }

        // Bricksforge Terminal
        if (get_option('brf_activated_tools') && in_array(8, get_option('brf_activated_tools')) && bricks_is_builder() && !bricks_is_builder_iframe()) {
            wp_enqueue_script('bricksforge-terminal');

            add_action('wp_enqueue_scripts', function () {
                $args = array(
                    'nonce'   => wp_create_nonce('wp_rest'),
                    'apiurl'  => get_rest_url() . "bricksforge/v1/",
                    'history' => get_option('brf_terminal_history'),
                );

                wp_localize_script('bricksforge-terminal', 'BRFTERMINAL', $args);
            });
        }

        // Global Vars
        add_action('wp_enqueue_scripts', function () {
            $args = array(
                'nonce'                     => wp_create_nonce('wp_rest'),
                'siteurl'                   => get_option('siteurl'),
                'postId'                    => get_the_ID(),
                'pluginurl'                 => BRICKSFORGE_URL,
                'apiurl'                    => get_rest_url() . "bricksforge/v1/",
                'brfGlobalClassesActivated' => get_option('brf_global_classes_activated'),
                'brfActivatedTools'         => get_option('brf_activated_tools'),
                'panel'                     => get_option('brf_panel'),
                'panelActivated'            => get_option('brf_activated_tools') && in_array(6, get_option('brf_activated_tools')),
                'aiEnabled'               => get_option('brf_activated_tools') && in_array(14, get_option('brf_activated_tools')),
            );

            if (bricks_is_builder()) {
                $args['permissions'] = get_option('brf_permissions_roles');
                $args['currentUserRole'] = $this->get_current_user_role();
            }

            wp_localize_script('bricksforge-panel', 'BRFPANEL', $args);
        });
    }

    public function get_current_user_role()
    {
        global $current_user;

        $user_roles = $current_user->roles;
        $user_role = array_shift($user_roles);

        return $user_role;
    }

    public function load_instance($instance)
    {
        return true;
    }

    /**
     * Render frontend app
     *
     * @param  array $atts
     * @param  string $content
     *
     * @return string
     */
    public function render_frontend($atts, $content = '')
    {
        wp_enqueue_style('bricksforge-builder');
        wp_enqueue_style('bricksforge-style');
        wp_enqueue_script('bricksforge-builder');

        $content .= '<div id="bricksforge-triggers"></div>';

        return $content;
    }
}
