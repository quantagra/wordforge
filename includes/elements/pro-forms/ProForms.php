<?php

namespace Bricks;

use \Bricksforge\Helper\ElementsHelper as ElementsHelper;

if (!defined('ABSPATH'))
    exit;

class Brf_Pro_Forms extends \Bricks\Element
{

    public $category = 'bricksforge';
    public $name = 'brf-pro-forms';
    public $icon = 'ti-layout-cta-left';
    public $scripts = ['bricksForm', 'brfQuill', 'brfProForms'];
    public $nestable = true;
    private static $styles_created = false;

    private $turnstile_key;

    // Todo: Remove Validation for these fields
    private $required_independent_fields = ['hidden', 'step', 'calculation', 'heading', 'divider', 'turnstile', 'shortcode'];

    public function load()
    {
        parent::load();

        add_action('wp_enqueue_scripts', function () {
            $this->enqueue_form_styles();
        });
    }

    public function enqueue_form_styles($fields = null)
    {
        if (!function_exists('bricks_is_builder') || !class_exists('Bricks\Database')) {
            return;
        }

        if (bricks_is_builder() && (!isset($fields))) {
            return;
        }

        if (!bricks_is_builder() && !bricks_is_builder_call() && !ElementsHelper::exists_in_page($this->name)) {
            return;
        }

        if (self::$styles_created) {
            return;
        }

        if ($fields === null) {
            $fields = [];

            // Find all elements with name = "brf-pro-forms"
            $forms = ElementsHelper::get_elements_by_name('brf-pro-forms');

            if (empty($forms)) {
                return;
            }

            // Re-index
            $forms = array_values($forms);

            foreach ($forms as $form) {
                if (!isset($form['settings']) || !isset($form['settings']['fields'])) {
                    continue;
                }

                // If type of $form['settings']['fields'] is not array, skip
                if (!is_array($form['settings']['fields'])) {
                    continue;
                }

                // Instead of overwriting $fields each time, we append to it to get all values
                $fields = array_merge($fields, $form['settings']['fields']);
            }
        }

        // Render Styles
        $fields_to_style = [];
        $styles = [];
        $media_queries = [];

        if (!isset($fields) || empty($fields)) {
            return;
        }

        foreach ($fields as $field) {
            $widthKeys = array_filter(array_keys($field), function ($key) {
                return strpos($key, 'width') === 0;
            });

            if (!empty($widthKeys)) {
                $fields_to_style[] = $field;
            }
        }

        if (empty($fields_to_style)) {
            return;
        }

        // Remove duplicates
        $fields_to_style = array_map("unserialize", array_unique(array_map("serialize", $fields_to_style)));

        foreach ($fields_to_style as $index => $field) {
            $field_id = isset($field['id']) ? $field['id'] : '';
            $field_width = isset($field['width']) ? $field['width'] : false;

            // Find keys like $field['width:tablet']. Split it. The first part is the width, the second part is the breakpoint as string. We need to store this into vars
            $field_width_parts = array_filter(array_map(function ($key) use ($field) {
                if (strpos($key, 'width') === 0) {
                    $parts = explode(':', $key);
                    return [
                        'width' => $field[$key],
                        'breakpoint' => isset($parts[1]) ? $parts[1] : 'default',
                        'id' => $field['id'],
                    ];
                }
            }, array_keys($field)));

            foreach ($field_width_parts as $part) {
                if ($part['breakpoint'] === 'default') {
                    $styles[] = ".form-group[data-field-id='{$part['id']}'] { width: {$part['width']}%; }";
                    continue;
                }

                if (!class_exists('\Bricks\Breakpoints')) {
                    return;
                }

                // We use \Bricks\Breakpoints::get_breakpoint() to get the breakpoint value
                $breakpoint = \Bricks\Breakpoints::get_breakpoint_by('key', $part['breakpoint']);

                if ($breakpoint && isset($breakpoint['width'])) {
                    $breakpoint = $breakpoint['width']; // 1600
                }

                $media_queries[$breakpoint][] = ".form-group[data-field-id='{$part['id']}'] { width: {$part['width']}%; }";
            }
        }

        // Convert styles array to string
        $styles = implode('', $styles);

        // Convert media queries array to string
        foreach ($media_queries as $breakpoint => $rules) {
            $styles .= "@media (max-width: {$breakpoint}px) { " . implode('', $rules) . " }";
        }

        if (bricks_is_builder() || bricks_is_rest_call()) {
            return '<style>' . $styles . '</style>';
        } else {
            wp_add_inline_style('bricksforge-style', $styles);
        }

        self::$styles_created = true;
    }

    public function get_label()
    {
        return esc_html__("Pro Forms", 'bricksforge');
    }

    public function enqueue_scripts()
    {

        wp_enqueue_script('bricksforge-elements');

        if (isset($this->settings['enableRecaptcha'])) {
            wp_enqueue_script('bricks-google-recaptcha');
        }

        if (isset($this->settings['enableHCaptcha'])) {
            wp_enqueue_script('bricksforge-hcaptcha');
        }

        if (isset($this->settings['enableTurnstile'])) {
            wp_enqueue_script('bricksforge-turnstile');
        }

        if (bricks_is_builder()) {
            wp_enqueue_script('bricksforge-quill');
            wp_enqueue_style('bricksforge-quill-snow');
            wp_enqueue_style('bricksforge-quill-bubble');
            wp_enqueue_style('editor-buttons');
            wp_enqueue_script('tinymce', includes_url('js/tinymce/tinymce.min.js'), array(), false, true);
        }

        // Frontend: Load Flatpickr JS library (Element Form field with type 'date' is found)
        if (!bricks_is_builder() && !empty($this->settings['fields'])) {
            foreach ($this->settings['fields'] as $field) {
                if ($field['type'] === 'datepicker') {
                    if (!bricks_is_builder()) {
                        wp_enqueue_script('bricks-flatpickr');
                        wp_enqueue_style('bricks-flatpickr');
                    }

                    // Load datepicker localisation (@since 1.8.6)
                    $l10n = !empty($field['l10n']) ? $field['l10n'] : '';

                    if ($l10n) {
                        wp_enqueue_script('bricks-flatpickr-l10n', "https://npmcdn.com/flatpickr@4.6.13/dist/l10n/$l10n.js", ['bricks-flatpickr']);
                    }
                }

                // Quill Editor Assets
                if ($field['type'] === 'rich-text') {
                    $rich_style = isset($field['quillStyle']) ? $field['quillStyle'] : 'snow';

                    switch ($rich_style) {
                        case 'snow':
                            wp_enqueue_script('bricksforge-quill');
                            wp_enqueue_style('bricksforge-quill-snow');
                            break;
                        case 'bubble':
                            wp_enqueue_script('bricksforge-quill');
                            wp_enqueue_style('bricksforge-quill-bubble');
                            break;
                        case 'wordpress':
                            wp_enqueue_style('editor-buttons');
                            wp_enqueue_script('tinymce', includes_url('js/tinymce/tinymce.min.js'), array(), false, true);
                            break;
                    }
                }
            }
        }
    }

    public function set_control_groups()
    {
        $this->control_groups['fields'] = [
            'title' => esc_html__('Fields', 'bricks'),
            'tab'   => 'content',
        ];

        $this->control_groups['submitButton'] = [
            'title' => esc_html__('Submit button', 'bricks'),
            'tab'   => 'content',
        ];

        $this->control_groups['actions'] = [
            'title' => esc_html__('Actions', 'bricks'),
            'tab'   => 'content',
        ];

        $this->control_groups['email'] = [
            'title'    => esc_html__('Email', 'bricks'),
            'tab'      => 'content',
            'required' => ['actions', '=', 'email'],
        ];

        $this->control_groups['confirmation'] = [
            'title'    => esc_html__('Confirmation email', 'bricks'),
            'tab'      => 'content',
            'required' => ['actions', '=', 'email'],
        ];

        $this->control_groups['redirect'] = [
            'title'    => esc_html__('Redirect', 'bricks'),
            'tab'      => 'content',
            'required' => ['actions', '=', 'redirect'],
        ];

        $this->control_groups['mailchimp'] = [
            'title'    => 'Mailchimp',
            'tab'      => 'content',
            'required' => ['actions', '=', 'mailchimp'],
        ];

        $this->control_groups['sendgrid'] = [
            'title'    => 'Sendgrid',
            'tab'      => 'content',
            'required' => ['actions', '=', 'sendgrid'],
        ];

        $this->control_groups['registration'] = [
            'title'    => esc_html__('User Registration', 'bricks'),
            'tab'      => 'content',
            'required' => ['actions', '=', 'registration'],
        ];

        $this->control_groups['login'] = [
            'title'    => esc_html__('User Login', 'bricks'),
            'tab'      => 'content',
            'required' => ['actions', '=', 'login'],
        ];

        $this->control_groups['pro_forms_post_action_post_create'] = [
            'title'    => esc_html__('Create New Post', 'bricks'),
            'tab'      => 'content',
            'required' => ['actions', '=', 'post_create'],
        ];

        $this->control_groups['pro_forms_post_action_post_update'] = [
            'title'    => esc_html__('Update Post', 'bricks'),
            'tab'      => 'content',
            'required' => ['actions', '=', 'post_update'],
        ];

        $this->control_groups['pro_forms_post_action_update_post_meta'] = [
            'title'    => esc_html__('Update Post Meta', 'bricks'),
            'tab'      => 'content',
            'required' => ['actions', '=', 'update_post_meta'],
        ];

        $this->control_groups['pro_forms_post_action_add_option'] = [
            'title'    => esc_html__('Add Option', 'bricks'),
            'tab'      => 'content',
            'required' => ['actions', '=', 'add_option'],
        ];

        $this->control_groups['pro_forms_post_action_update_option'] = [
            'title'    => esc_html__('Update Option', 'bricks'),
            'tab'      => 'content',
            'required' => ['actions', '=', 'update_option'],
        ];

        $this->control_groups['pro_forms_post_action_delete_option'] = [
            'title'    => esc_html__('Delete Option', 'bricks'),
            'tab'      => 'content',
            'required' => ['actions', '=', 'delete_option'],
        ];

        $this->control_groups['updateUserMeta'] = [
            'title'    => esc_html__('Update User Meta', 'bricks'),
            'tab'      => 'content',
            'required' => ['actions', '=', 'update_user_meta'],
        ];

        $this->control_groups['resetUserPassword'] = [
            'title'    => esc_html__('Reset User Password', 'bricks'),
            'tab'      => 'content',
            'required' => ['actions', '=', 'reset_user_password'],
        ];

        $this->control_groups['pro_forms_post_action_set_storage_item'] = [
            'title'    => esc_html__('Set Storage Item', 'bricks'),
            'tab'      => 'content',
            'required' => ['actions', '=', 'set_storage_item'],
        ];

        $this->control_groups['submissions'] = [
            'title'    => esc_html__('Submissions', 'bricks'),
            'tab'      => 'content',
            'required' => ['actions', '=', 'create_submission'],
        ];

        $this->control_groups['wcAddToCart'] = [
            'title'    => esc_html__('WooCommerce: Add To Cart', 'bricks'),
            'tab'      => 'content',
            'required' => ['actions', '=', 'wc_add_to_cart'],
        ];

        $this->control_groups['webhook'] = [
            'title'    => esc_html__('Webhook', 'bricks'),
            'tab'      => 'content',
            'required' => ['actions', '=', 'webhook'],
        ];

        $this->control_groups['spam'] = [
            'title' => esc_html__('Spam protection', 'bricks'),
            'tab'   => 'content',
        ];

        $this->control_groups['notifications'] = [
            'title' => esc_html__('Notifications', 'bricks'),
            'tab'   => 'content',
        ];

        $this->control_groups['multistep'] = [
            'title' => esc_html__('Multi Step', 'bricks'),
            'tab'   => 'content',
        ];

        $this->control_groups['multistepSummary'] = [
            'title'    => esc_html__('Multi Step Summary', 'bricks'),
            'tab'      => 'content',
            'required' => ['multiStepSummary', '=', true],
        ];

        $this->control_groups['multistepStep'] = [
            'title'    => esc_html__('Step Settings', 'bricks'),
            'tab'      => 'content',
            'required' => ['multiStepShowSteps', '=', true],
        ];

        $this->control_groups['other'] = [
            'title'    => esc_html__('Other', 'bricks'),
            'tab'      => 'content',
        ];
    }

    public function set_controls()
    {
        // Nestable Forms Info
        $this->controls['nestableFormsInfo'] = [
            'tab'      => 'content',
            'type'     => 'info',
            'content'  => esc_html__('Nestable Pro Forms (Experimental): You can nest form fields inside a Pro Form. This allows you to create complex forms using single Bricks Elements. This feature is experimental and should only be used in staging environments.', 'bricks'),
        ];

        // Group: Fields
        $this->controls['fields'] = [
            'tab'           => 'content',
            'group'         => 'fields',
            'placeholder'   => esc_html__('Form Field', 'bricks'),
            'type'          => 'repeater',
            'selector'      => '.form-group',
            'titleProperty' => 'label',
            'fields'        => [
                'type'                   => [
                    'label'     => esc_html__('Type', 'bricks'),
                    'type'      => 'select',
                    'options'   => [
                        'email'      => esc_html__('Email', 'bricks'),
                        'text'       => esc_html__('Text', 'bricks'),
                        'textarea'   => esc_html__('Textarea', 'bricks'),
                        'rich-text'   => esc_html__('Rich Text', 'bricks'),
                        'tel'        => esc_html__('Tel', 'bricks'),
                        'number'     => esc_html__('Number', 'bricks'),
                        'url'        => esc_html__('URL', 'bricks'),
                        'checkbox'   => esc_html__('Checkbox', 'bricks'),
                        'select'     => esc_html__('Select', 'bricks'),
                        'radio'      => esc_html__('Radio', 'bricks'),
                        'file'       => esc_html__('File upload', 'bricks'),
                        'password'   => esc_html__('Password', 'bricks'),
                        'datepicker' => esc_html__('Datepicker', 'bricks'),
                        'calculation' => esc_html__('Calculation', 'bricks'),
                        'shortcode' => esc_html__('Shortcode', 'bricks'),
                        'hidden'     => esc_html__('Hidden', 'bricks'),
                        'heading' => esc_html__('Heading', 'bricks'),
                        'divider' => esc_html__('Divider', 'bricks'),
                        'turnstile'  => esc_html__('Turnstile', 'bricks'),
                        'step'       => esc_html__('Step', 'bricks'),
                        'groupStart' => esc_html__('Group Start', 'bricks'),
                        'groupEnd'   => esc_html__('Group End', 'bricks'),
                    ],
                    'clearable' => false,
                ],

                'quillStyle' => [
                    'label' => esc_html__('Style', 'bricks'),
                    'type'  => 'select',
                    'options' => [
                        'snow' => esc_html__('Flat Toolbar', 'bricks'),
                        'bubble' => esc_html__('Tooltip Based', 'bricks'),
                        'wordpress' => esc_html__('WordPress', 'bricks'),
                    ],
                    'default' => 'default',
                    'description' => esc_html__('Default: Flat Toolbar', 'bricks'),
                    'required' => ['type', '=', 'rich-text'],
                ],

                'min'                    => [
                    'label'    => esc_html__('Min', 'bricks'),
                    'type'     => 'number',
                    'min'      => 0,
                    'max'      => 100,
                    'required' => ['type', '=', ['number']],
                ],

                'max'                    => [
                    'label'    => esc_html__('Max', 'bricks'),
                    'type'     => 'number',
                    'min'      => 0,
                    'max'      => 100,
                    'required' => ['type', '=', ['number']],
                ],

                'headingTag' => [
                    'label' => esc_html__('HTML Tag', 'bricks'),
                    'type'  => 'select',
                    'options' => [
                        'h1' => 'H1',
                        'h2' => 'H2',
                        'h3' => 'H3',
                        'h4' => 'H4',
                        'h5' => 'H5',
                        'h6' => 'H6',
                    ],
                    'default' => 'h3',
                    'required' => ['type', '=', ['heading']],
                ],

                'label'                  => [
                    'label' => esc_html__('Label', 'bricks'),
                    'type'  => 'text',
                ],

                'headingAddDescription' => [
                    'label' => esc_html__('Add description', 'bricks'),
                    'type'  => 'checkbox',
                    'required' => ['type', '=', ['heading']],
                ],

                'headingDescription' => [
                    'label' => esc_html__('Description', 'bricks'),
                    'type'  => 'textarea',
                    'required' => [['type', '=', ['heading']], ['headingAddDescription', '=', true]],
                ],

                'info' => [
                    'label' => esc_html__('Info', 'bricks'),
                    'type'  => 'info',
                    'required' => ['type', '=', ['calculation']],
                    'content' => esc_html__('To use form IDs, wrap them in curly braces {}. Example: {mdityr} + 50 / 2', 'bricks'),
                ],

                'formula' => [
                    'label' => esc_html__('Formula', 'bricks'),
                    'type'  => 'textarea',
                    'required' => ['type', '=', ['calculation']],
                ],

                'roundValue' => [
                    'label' => esc_html__('Round Value', 'bricks'),
                    'type'  => 'checkbox',
                    'required' => ['type', '=', ['calculation']],
                    'default' => false,
                    'description' => esc_html__('If checked, the value will be rounded to the nearest integer.', 'bricks'),
                ],

                'hasCurrencyFormat' => [
                    'label' => esc_html__('Currency Format', 'bricks'),
                    'type'  => 'checkbox',
                    'required' => ['type', '=', ['calculation']],
                    'default' => false,
                    'description' => esc_html__('If checked, the value will be formatted with two decimal places.', 'bricks'),
                ],

                'setEmptyToZero' => [
                    'label' => esc_html__('Set empty to 0', 'bricks'),
                    'type'  => 'checkbox',
                    'required' => ['type', '=', ['calculation']],
                    'default' => true,
                    'description' => esc_html__('If checked, empty fields will be set to 0.', 'bricks'),
                ],

                'emptyMessage' => [
                    'label' => esc_html__('Empty message', 'bricks'),
                    'type'  => 'text',
                    'default' => 'Please fill in all fields.',
                    'required' => [['type', '=', ['calculation']], ['setEmptyToZero', '=', false]],
                    'description' => esc_html__('The message you want to show if the calculation is invalid because of empty fields ', 'bricks'),
                ],

                'onlyRemote' => [
                    'label' => esc_html__('Only Remote', 'bricks'),
                    'type'  => 'checkbox',
                    'required' => ['type', '=', ['calculation']],
                    'default' => false,
                    'description' => esc_html__('If checked, the calculation input will be hidden. Use Dynamic Data to show the calculation value', 'bricks'),
                ],

                'shortcode' => [
                    'label' => esc_html__('Shortcode', 'bricks'),
                    'type'  => 'text',
                    'placeholder' => esc_html__('[bricks_template id=8507]', 'bricks'),
                    'required' => ['type', '=', ['shortcode']],
                ],


                'placeholder'            => [
                    'label'    => esc_html__('Placeholder', 'bricks'),
                    'type'     => 'text',
                    'required' => [['type', '!=', ['file', 'hidden', 'step', 'calculation', 'heading', 'divider', 'turnstile', 'shortcode', 'groupStart', 'groupEnd']], ['quillStyle', '!=', 'wordpress']],
                ],


                'quillFormats' => [
                    'label' => esc_html__('Formats', 'bricks'),
                    'type'  => 'select',
                    'options' => $this->get_quill_formats(),
                    'multiple' => true,
                    'description' => esc_html__('Default: Headings, Bold, Italic, Underline, Link', 'bricks'),
                    'required' => [['type', '=', 'rich-text'], ['quillStyle', '!=', 'wordpress']],
                ],

                'mceFormatsInfo' => [
                    'label' => esc_html__('Info', 'bricks'),
                    'type'  => 'info',
                    'required' => [['type', '=', 'rich-text'], ['quillStyle', '=', 'wordpress']],
                    'content' => esc_html__('A list of toolbar buttons you can use can be found here:', 'bricks') . ' <a target="_blank" href="https://www.tiny.cloud/docs/tinymce/6/available-toolbar-buttons/">TinyMCE Docs</a>',

                ],

                'mceFormats' => [
                    'label' => esc_html__('Formats', 'bricks'),
                    'type'  => 'text',
                    'description' => esc_html__('Example: undo redo | formatselect | bold italic ', 'bricks'),
                    'required' => [['type', '=', 'rich-text'], ['quillStyle', '=', 'wordpress']],
                ],


                'quillUseBricksColors' => [
                    'label' => esc_html__('Use Bricks Colors', 'bricks'),
                    'type'  => 'checkbox',
                    'default' => true,
                    'description' => esc_html__('If checked, the editor will use the colors defined in a Bricks Color Palette ', 'bricks'),
                    'required' => [['type', '=', 'rich-text'], ['quillStyle', '!=', 'wordpress']],
                ],

                'quillBricksColorPalette' => [
                    'label' => esc_html__('Color Palette', 'bricks'),
                    'type'  => 'select',
                    'options' => $this->get_color_palettes(),
                    'default' => 'default',
                    'description' => esc_html__('Choose your Color Palette', 'bricks'),
                    'required' => [['type', '=', 'rich-text'], ['quillUseBricksColors', '=', true], ['quillStyle', '!=', 'wordpress']],
                ],


                // Rich Text
                'quillInitialHeight' => [
                    'label'    => esc_html__('Min Height', 'bricks'),
                    'type'     => 'number',
                    'units'    => true,
                    'css'      => [
                        [
                            'property' => 'min-height',
                            'selector' => '.brf-rich-text-container',
                        ],
                        [
                            'property' => 'min-height',
                            'selector' => '.mce-panel iframe',
                        ]
                    ],
                    'description' => esc_html__('Default: 120px', 'bricks'),
                    'required' => ['type', '=', 'rich-text'],
                ],

                'quillReadOnly' => [
                    'label' => esc_html__('Read Only', 'bricks'),
                    'type'  => 'checkbox',
                    'default' => false,
                    'description' => esc_html__('If checked, the editor will be read only.', 'bricks'),
                    'required' => [['type', '=', 'rich-text'], ['quillStyle', '!=', 'wordpress']],
                ],

                'value'                  => [
                    'label'    => esc_html__('Value', 'bricks'),
                    'type'     => 'text',
                    'required' => ['type', '=', ['hidden']],
                ],

                'autocomplete' => [
                    'label' => esc_html__('Autocomplete', 'bricks'),
                    'type'  => 'select',
                    'default' => 'off',
                    'options' => $this->autocomplete_options(),
                    'description' => esc_html__('If checked, you allow the browser to autocomplete the value.', 'bricks'),
                    'required' => ['type', '=', ['text', 'email', 'password', 'number', 'tel', 'url']],
                ],

                'initValue' => [
                    'label'    => esc_html__('Initial Value', 'bricks'),
                    'type'     => 'text',
                    'required' => ['type', '!=', ['hidden', 'step', 'calculation', 'heading', 'divider', 'turnstile', 'shortcode', 'groupStart', 'groupEnd']],
                ],

                'fileUploadSeparator'    => [
                    'label'    => esc_html__('File upload', 'bricks'),
                    'type'     => 'separator',
                    'required' => ['type', '=', 'file'],
                ],

                'fileUploadButtonText'   => [
                    'type'        => 'text',
                    'placeholder' => esc_html__('Choose files', 'bricks'),
                    'default'     => esc_html__('Choose files', 'bricks'),
                    'required'    => ['type', '=', 'file'],
                ],

                'fileUploadLimit'        => [
                    'label'    => esc_html__('max. files', 'bricks'),
                    'type'     => 'number',
                    'min'      => 1,
                    'max'      => 50,
                    'required' => ['type', '=', 'file'],
                ],

                'fileUploadSize'         => [
                    'label'    => esc_html__('Max. size', 'bricks') . ' (MB)',
                    'type'     => 'number',
                    'min'      => 1,
                    'max'      => 50,
                    'required' => ['type', '=', 'file'],
                ],

                'width'                  => [
                    'label'       => esc_html__('Width', 'bricks') . ' (%)',
                    'type'        => 'number',
                    'unit'        => '%',
                    'min'         => 0,
                    'max'         => 100,
                    'placeholder' => 100,
                    'rerender'   => true,
                    'css'         => [
                        [
                            'selector' => 'dummy',
                            'property' => 'width',
                        ],
                    ],
                    'required'    => ['type', '!=', ['hidden', 'step', 'heading', 'turnstile', 'groupStart', 'groupEnd']],
                ],

                'height'                 => [
                    'label'    => esc_html__('Height', 'bricks'),
                    'type'     => 'number',
                    'units'    => true,
                    'css'      => [
                        [
                            'property' => 'height'
                        ],
                    ],
                    'required' => ['type', '=', ['textarea']],
                ],

                'fileUploadAllowedTypes' => [
                    'label'       => esc_html__('Allowed file types', 'bricks'),
                    'placeholder' => 'pdf,jpg,...',
                    'type'        => 'text',
                    'required'    => ['type', '=', 'file'],
                ],

                'hideFileNamePreview' => [
                    'label' => esc_html__('Hide file name text preview', 'bricks'),
                    'type'  => 'checkbox',
                    'default' => false,
                    'description' => esc_html__('If checked, the file name preview will be hidden.', 'bricks'),
                    'required' => ['type', '=', 'file'],
                ],

                'hideImagePreview' => [
                    'label' => esc_html__('Hide Image Preview', 'bricks'),
                    'type'  => 'checkbox',
                    'default' => true,
                    'description' => esc_html__('If checked, the image preview will be hidden.', 'bricks'),
                    'required' => ['type', '=', 'file'],
                ],

                // @since 1.4 (File upload button style here)
                'fileUploadTypography'   => [
                    'tab'      => 'content',
                    'label'    => esc_html__('Typography', 'bricks'),
                    'type'     => 'typography',
                    'css'      => [
                        [
                            'property' => 'font',
                            'selector' => '.choose-files',
                        ],
                    ],
                    'required' => ['type', '=', 'file'],
                ],

                'fileUploadBackground'   => [
                    'tab'      => 'content',
                    'label'    => esc_html__('Background', 'bricks'),
                    'type'     => 'color',
                    'css'      => [
                        [
                            'property' => 'background-color',
                            'selector' => '.choose-files',
                        ],
                    ],
                    'required' => ['type', '=', 'file'],
                ],

                'fileUploadBorder'       => [
                    'tab'      => 'content',
                    'label'    => esc_html__('Border', 'bricks'),
                    'type'     => 'border',
                    'css'      => [
                        [
                            'property' => 'border',
                            'selector' => '.choose-files',
                        ],
                    ],
                    'required' => ['type', '=', 'file'],
                ],

                'time'                   => [
                    'label'    => esc_html__('Enable time', 'bricks'),
                    'type'     => 'checkbox',
                    'required' => ['type', '=', ['datepicker']],
                ],

                'l10n'                   => [
                    'label'       => esc_html__('Language', 'bricks'),
                    'type'        => 'text',
                    'inline'      => true,
                    'description' => sprintf(
                        '<a href="https://github.com/flatpickr/flatpickr/tree/master/src/l10n" target="_blank">%s</a> (de, es, fr, etc.)',
                        esc_html__('Language codes', 'bricks'),
                    ),
                    'required' => ['type', '=', ['datepicker']],
                ],

                'dateFormat' => [
                    'label'       => esc_html__('Date Format', 'bricks'),
                    'type'        => 'text',
                    'placeholder' => esc_html__('Y-m-d H:i', 'bricks'),
                    'required' => ['type', '=', ['datepicker']],
                ],

                'minTime'                => [
                    'label'       => esc_html__('Min. time', 'bricks'),
                    'type'        => 'text',
                    'placeholder' => esc_html__('09:00', 'bricks'),
                    'required'    => ['time', '!=', ''],
                ],

                'maxTime'                => [
                    'label'       => esc_html__('Max. time', 'bricks'),
                    'type'        => 'text',
                    'placeholder' => esc_html__('20:00', 'bricks'),
                    'required'    => ['time', '!=', ''],
                ],

                // Enable Range
                'dateRange'                  => [
                    'label'    => esc_html__('Range Picker', 'bricks'),
                    'type'     => 'checkbox',
                    'required' => ['type', '=', ['datepicker']],
                ],

                // Needs Enable Dates (Checkbox)
                'needsEnableDates' => [
                    'label'    => esc_html__('Enable specific dates', 'bricks'),
                    'type'     => 'checkbox',
                    'required' => [['type', '=', ['datepicker']]],
                ],

                // Enable Dates Source
                'enableDatesSource'                  => [
                    'label'    => esc_html__('Enable Dates Source', 'bricks'),
                    'type'     => 'select',
                    'options' => [
                        'custom' => esc_html__('Custom', 'bricks'),
                        'dynamic' => esc_html__('Dynamic Data', 'bricks'),
                    ],
                    'default' => 'custom',
                    'required' => [['type', '=', ['datepicker']], ['needsEnableDates', '=', true]],
                ],

                // Enable Dates (Repeater)
                'enableDates' => [
                    'label'    => esc_html__('Dates To Enable', 'bricks'),
                    'type'     => 'repeater',
                    'fields'   => [
                        'from' => [
                            'label' => esc_html__('From Date', 'bricks'),
                            'type'  => 'datepicker',
                            'placeholder' => esc_html__('YYYY-MM-DD', 'bricks'),
                        ],
                        'to' => [
                            'label' => esc_html__('To Date', 'bricks'),
                            'type'  => 'datepicker',
                            'placeholder' => esc_html__('YYYY-MM-DD', 'bricks'),
                        ],
                    ],
                    'required' => [['type', '=', ['datepicker']], ['enableDatesSource', '=', 'custom'], ['needsEnableDates', '=', true]],
                ],

                'enableDatesDynamic' => [
                    'label' => esc_html__('Dates To Enable', 'bricks'),
                    'type'  => 'text',
                    'required' => [['type', '=', ['datepicker']], ['enableDatesSource', '=', 'dynamic'], ['needsEnableDates', '=', true]],
                ],

                // Enable specific weekdays
                'needsEnableWeekdays' => [
                    'label'    => esc_html__('Enable specific weekdays', 'bricks'),
                    'type'     => 'checkbox',
                    'required' => [['type', '=', ['datepicker']]],
                ],

                // (Weekdays) Multi Select
                'enableWeekdaysData' => [
                    'label'    => esc_html__('Weekdays to enable', 'bricks'),
                    'type'     => 'select',
                    'multiple' => true,
                    'inline' => true,
                    'options' => [
                        1 => esc_html__('Monday', 'bricks'),
                        2 => esc_html__('Tuesday', 'bricks'),
                        3 => esc_html__('Wednesday', 'bricks'),
                        4 => esc_html__('Thursday', 'bricks'),
                        5 => esc_html__('Friday', 'bricks'),
                        6 => esc_html__('Saturday', 'bricks'),
                        7 => esc_html__('Sunday', 'bricks'),
                    ],
                    'required' => [['type', '=', ['datepicker']], ['needsEnableWeekdays', '=', true]],
                ],

                // Needs Disable Dates (Checkbox)
                'needsDisableDates' => [
                    'label'    => esc_html__('Disable specific dates', 'bricks'),
                    'type'     => 'checkbox',
                    'required' => [['type', '=', ['datepicker']]],
                ],

                // Disable Dates Source
                'disableDatesSource'                  => [
                    'label'    => esc_html__('Disable Dates Source', 'bricks'),
                    'type'     => 'select',
                    'options' => [
                        'custom' => esc_html__('Custom', 'bricks'),
                        'dynamic' => esc_html__('Dynamic Data', 'bricks'),
                    ],
                    'default' => 'custom',
                    'required' => [['type', '=', ['datepicker']], ['needsDisableDates', '=', true]],
                ],

                // Disable Dates (Repeater)
                'disableDates' => [
                    'label'    => esc_html__('Dates To Disable', 'bricks'),
                    'type'     => 'repeater',
                    'fields'   => [
                        'from' => [
                            'label' => esc_html__('From Date', 'bricks'),
                            'type'  => 'datepicker',
                            'placeholder' => esc_html__('YYYY-MM-DD', 'bricks'),
                        ],
                        'to' => [
                            'label' => esc_html__('To Date', 'bricks'),
                            'type'  => 'datepicker',
                            'placeholder' => esc_html__('YYYY-MM-DD', 'bricks'),
                        ],
                    ],
                    'required' => [['type', '=', ['datepicker']], ['disableDatesSource', '=', 'custom'], ['needsDisableDates', '=', true]],
                ],

                // Disable Dates Dynamic
                'disableDatesDynamic' => [
                    'label' => esc_html__('Dates To Disable', 'bricks'),
                    'type'  => 'text',
                    'required' => [['type', '=', ['datepicker']], ['disableDatesSource', '=', 'dynamic'], ['needsDisableDates', '=', true]],
                ],

                'showVisualCalendar' => [
                    'label' => esc_html__('Show Visual Calendar', 'bricks'),
                    'type'  => 'checkbox',
                    'default' => true,
                    'description' => esc_html__('If checked, the visual calendar will be shown. The input field will be hidden.', 'bricks'),
                    'required' => ['type', '=', ['datepicker']],
                    'css' => [
                        [
                            'property' => 'display',
                            'value' => 'none',
                            'selector' => '.flatpickr.form-control.input',
                        ],
                    ],

                ],

                'stripHTML' => [
                    'label' => esc_html__('Strip HTML', 'bricks'),
                    'type'  => 'checkbox',
                    'default' => false,
                    'description' => esc_html__('If checked, all HTML tags will be stripped from the output. By default, not dangerous tags are allowed.', 'bricks'),
                    'required' => ['type', '=', ['text', 'textarea', 'hidden']],
                ],

                'maxLength' => [
                    'label' => esc_html__('Max Length', 'bricks'),
                    'type'  => 'number',
                    'min'   => 1,
                    'description' => esc_html__('If set, the input will be limited to the given number of characters.', 'bricks'),
                    'required' => ['type', '=', ['text', 'textarea', 'email', 'number', 'password', 'url', 'tel']],
                ],

                'required'               => [
                    'label'    => esc_html__('Required', 'bricks'),
                    'type'     => 'checkbox',
                    'inline'   => true,
                    'required' => ['type', '!=', ['hidden', 'step', 'calculation', 'heading', 'divider', 'turnstile']],
                ],

                'options'                => [
                    'label'    => esc_html__('Options (one per line)', 'bricks'),
                    'type'     => 'textarea',
                    'required' => ['type', '=', ['checkbox', 'select', 'radio']],
                ],

                'hasConditions' => [
                    'label' => esc_html__('Add Conditions', 'bricks'),
                    'type'  => 'checkbox',
                    'default' => false,
                    'required' => ['type', '!=', ['hidden', 'step', 'turnstile']],
                ],

                'ignoreCustomStyles' => [
                    'label' => esc_html__('Ignore Custom Styles', 'bricks'),
                    'type'  => 'checkbox',
                    'default' => false,
                    'description' => esc_html__('If checked, the custom styles will be ignored.', 'bricks'),
                    'required' => ['type', '=', ['checkbox', 'radio']],
                ],

                'conditions' => [
                    'label' => esc_html__('Conditions', 'bricks'),
                    'type'  => 'repeater',
                    'required' => [['type', '!=', ['hidden', 'step', 'turnstile']], ['hasConditions', '=', true]],
                    'titleProperty' => 'condition',
                    'fields'        => [
                        'postId'   => [
                            'label'       => esc_html__('Post ID', 'bricks'),
                            'type'        => 'text',
                            'placeholder' => 'Leave Empty For Current Post ID',
                            'required'    => [['condition', '=', 'post_meta']],
                        ],
                        'condition'         => [
                            'tab'     => 'content',
                            'group'   => 'submitButton',
                            'type'    => 'select',
                            'options' => $this->get_field_conditions(),
                            'default' => 'option'
                        ],

                        'value'    => [
                            'required' => [['condition'], ['condition', '!=', 'submission_count_reached']],
                            'tab'      => 'content',
                            'group'    => 'submitButton',
                            'type'     => 'text',
                            'default'  => ''
                        ],

                        'operator' => [
                            'required' => [['value'], ['condition', '!=', 'submission_count_reached']],
                            'tab'      => 'content',
                            'group'    => 'submitButton',
                            'type'     => 'select',
                            'options'  => $this->get_condition_operators(),
                            'default'  => '=='
                        ],

                        'value2'   => [
                            'required' => [['operator'], ['value', '!=', ''], ['condition', '!=', 'submission_count_reached'], ['operator', '!=', ['exists', 'not_exists', 'empty', 'not_empty']]],
                            'tab'      => 'content',
                            'group'    => 'submitButton',
                            'type'     => 'text',
                            'default'  => ''
                        ],

                        'type'     => [
                            'required' => [['condition', '!=', 'submission_count_reached']],
                            'tab'      => 'content',
                            'group'    => 'submitButton',
                            'label'    => esc_html__('Data Type', 'bricks'),
                            'type'     => 'select',
                            'options'  => $this->get_condition_data_types(),
                            'default'  => 'string'
                        ]
                    ]
                ],

                'conditionsRelation' => [
                    'label' => esc_html__('Conditions Relation', 'bricks'),
                    'type'  => 'select',
                    'required' => [['type', '!=', ['hidden', 'step', 'heading', 'divider', 'turnstile']], ['hasConditions', '=', true]],
                    'options' => [
                        'and' => esc_html__('AND', 'bricks'),
                        'or'  => esc_html__('OR', 'bricks'),
                    ],
                    'default' => 'and'
                ],
                'icon' => [
                    'label' => esc_html__('Icon', 'bricks'),
                    'type'  => 'icon',
                    'required' => ['type', '=', ['text', 'email', 'password', 'number', 'tel', 'url', 'rich-text', 'datepicker', 'calculation', 'select']],
                ],

                'cssClass' => [
                    'label' => esc_html__('CSS Class', 'bricks'),
                    'type'  => 'text',
                    'inline'         => true,
                    'required' => ['type', '!=', ['groupEnd']],
                ],
                'id'                     => [
                    'label'          => esc_html__('ID', 'bricks'),
                    'type'           => 'text',
                    'inline'         => true,
                    'spellcheck'     => false,
                    'hasDynamicData' => false,
                    'required'       => ['type', '!=', ['step']],
                    'readonly'       => true,
                    'editable'       => false,
                ],

            ],
            'default'       => [
                [
                    'type'        => 'text',
                    'label'       => esc_html__('Name', 'bricks'),
                    'placeholder' => esc_html__('Your Name', 'bricks'),
                    'id'          => Helpers::generate_random_id(false),
                ],
                [
                    'type'        => 'email',
                    'label'       => esc_html__('Email', 'bricks'),
                    'placeholder' => esc_html__('Your Email', 'bricks'),
                    'required'    => true,
                    'id'          => Helpers::generate_random_id(false),
                ],
                [
                    'type'        => 'textarea',
                    'label'       => esc_html__('Message', 'bricks'),
                    'placeholder' => esc_html__('Your Message', 'bricks'),
                    'required'    => true,
                    'id'          => Helpers::generate_random_id(false),
                ],
            ],
        ];

        $this->controls['requiredAsterisk'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Show required asterisk', 'bricks'),
            'type'  => 'checkbox',
        ];

        $this->controls['showLabels'] = [
            'tab'     => 'content',
            'group'   => 'fields',
            'label'   => esc_html__('Show labels', 'bricks'),
            'type'    => 'checkbox',
            'default' => true
        ];

        $this->controls['labelTypography'] = [
            'tab'      => 'content',
            'group'    => 'fields',
            'label'    => esc_html__('Label typography', 'bricks'),
            'type'     => 'typography',
            'css'      => [
                [
                    'property' => 'font',
                    'selector' => 'label',
                ],
            ],
            'required' => ['showLabels'],
        ];

        $this->controls['placeholderTypography'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Placeholder typography', 'bricks'),
            'type'  => 'typography',
            'css'   => [
                [
                    'property' => 'font',
                    'selector' => '::placeholder',
                ],
                [
                    'property' => 'font',
                    'selector' => 'select',
                ],
                [
                    'property' => 'font',
                    'selector' => '.ql-editor.ql-blank::before',
                    'important' => true
                ],

            ],
        ];

        // Field

        $this->controls['fieldSeparator'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Field', 'bricks'),
            'type'  => 'separator',
        ];

        $this->controls['fieldMargin'] = [
            'tab'         => 'content',
            'group'       => 'fields',
            'label'       => esc_html__('Margin', 'bricks'),
            'type'        => 'spacing',
            'css'         => [
                // Use padding (as margin results in line-breaks)
                [
                    'property' => 'padding',
                    'selector' => '.form-group:not(.submit-button-wrapper)',
                ],
            ],
            'placeholder' => [
                'top'    => 0,
                'right'  => 0,
                'bottom' => '20px',
                'left'   => 0,
            ],
        ];

        $this->controls['fieldPadding'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Padding', 'bricks'),
            'type'  => 'spacing',
            'css'   => [
                [
                    'property' => 'padding',
                    'selector' => '.form-group input',
                ],
                [
                    'property' => 'padding',
                    'selector' => '.flatpickr',
                ],
                [
                    'property' => 'padding',
                    'selector' => 'select',
                ],
                [
                    'property' => 'padding',
                    'selector' => 'textarea',
                ],
            ],
        ];

        $this->controls['horizontalAlignFields'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Alignment', 'bricks'),
            'type'  => 'justify-content',
            'css'   => [
                [
                    'property' => 'justify-content',
                ],
                [
                    'property' => 'justify-content',
                    'selector' => '.is-group',
                ],
            ],
        ];

        $this->controls['fieldBackgroundColor'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Background color', 'bricks'),
            'type'  => 'color',
            'css'   => [
                [
                    'property' => 'background-color',
                    'selector' => '.form-group input',
                ],
                [
                    'property' => 'background-color',
                    'selector' => '.flatpickr',
                ],
                [
                    'property' => 'background-color',
                    'selector' => 'select',
                ],
                [
                    'property' => 'background-color',
                    'selector' => 'textarea',
                ],
            ],
        ];

        $this->controls['fieldBorder'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Border', 'bricks'),
            'type'  => 'border',
            'css'   => [
                [
                    'property' => 'border',
                    'selector' => '.form-group input',
                ],
                [
                    'property' => 'border',
                    'selector' => '.flatpickr',
                ],
                [
                    'property' => 'border',
                    'selector' => 'select',
                ],
                [
                    'property' => 'border',
                    'selector' => 'textarea',
                ],
                [
                    'property' => 'border',
                    'selector' => '.bricks-button',
                ],
                [
                    'property' => 'border',
                    'selector' => '.choose-files',
                ],
            ],
        ];

        // Box Shadow
        $this->controls['fieldBoxShadow'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Box shadow', 'bricks'),
            'type'  => 'box-shadow',
            'css'   => [
                [
                    'property' => 'box-shadow',
                    'selector' => '.form-group input',
                ],
                [
                    'property' => 'box-shadow',
                    'selector' => '.flatpickr',
                ],
                [
                    'property' => 'box-shadow',
                    'selector' => 'select',
                ],
                [
                    'property' => 'box-shadow',
                    'selector' => 'textarea',
                ],
                [
                    'property' => 'box-shadow',
                    'selector' => '.bricks-button',
                ],
                [
                    'property' => 'box-shadow',
                    'selector' => '.choose-files',
                ],
            ],
        ];

        $this->controls['fieldTypography'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Typography', 'bricks'),
            'type'  => 'typography',
            'css'   => [
                [
                    'property' => 'font',
                    'selector' => '.form-group input',
                ],
                [
                    'property' => 'font',
                    'selector' => 'select',
                ],
                [
                    'property' => 'font',
                    'selector' => 'textarea',
                ],
            ],
        ];

        // Separator: Checkboxes
        $this->controls['checkboxSeparator'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Checkboxes', 'bricks'),
            'type'  => 'separator',
        ];

        // Enable Custom Style
        $this->controls['checkboxCustomStyle'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Custom Style', 'bricks'),
            'type'  => 'checkbox',
            'default' => false,
            'rerender' => true,
            'description' => esc_html__('If checked, the checkboxes will be styled with CSS instead of the browser default.', 'bricks'),
            'css' => [
                [
                    'property' => 'appearance',
                    'value' => 'none',
                    'selector' => '&[data-checkbox-custom] .form-group:not([data-ignore-custom-styles]) input[type="checkbox"]',
                ],
                [
                    'property' => '-webkit-appearance',
                    'value' => 'none',
                    'selector' => '&[data-checkbox-custom] .form-group:not([data-ignore-custom-styles]) input[type="checkbox"]',
                ],
                [
                    'property' => 'display',
                    'value' => 'flex',
                    'selector' => '&[data-checkbox-custom] [data-field-type="checkbox"]:not([data-ignore-custom-styles]) .options-wrapper li',
                ],
                [
                    'property' => 'flex-direction',
                    'value' => 'row',
                    'selector' => '&[data-checkbox-custom] [data-field-type="checkbox"]:not([data-ignore-custom-styles]) .options-wrapper li',
                ],
                [
                    'property' => 'align-items',
                    'value' => 'center',
                    'selector' => '&[data-checkbox-custom] [data-field-type="checkbox"]:not([data-ignore-custom-styles]) .options-wrapper li',
                ],
            ],
        ];

        // Width
        $this->controls['checkboxWidth'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Checkbox Width', 'bricks'),
            'type'  => 'number',
            'units' => true,
            'unit'  => 'px',
            'min'   => 0,
            'max'   => 100,
            'css'   => [
                [
                    'property' => 'width',
                    'selector' => '&[data-checkbox-custom] .form-group:not([data-ignore-custom-styles]) input[type="checkbox"]',
                ],
            ],
            'required' => [['checkboxCustomStyle', '=', true], ['checkboxCard', '=', false]],
        ];

        // Height
        $this->controls['checkboxHeight'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Checkbox Height', 'bricks'),
            'type'  => 'number',
            'unit' => 'px',
            'units' => true,
            'css'   => [
                [
                    'property' => 'height',
                    'selector' => '&[data-checkbox-custom] .form-group:not([data-ignore-custom-styles]) input[type="checkbox"]',
                ],
            ],
            'required' => [['checkboxCustomStyle', '=', true], ['checkboxCard', '=', false]],
        ];

        // Card Checkbox
        $this->controls['checkboxCard'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Is Card', 'bricks'),
            'type'  => 'checkbox',
            'default' => false,
            'rerender' => true,
            'required' => ['checkboxCustomStyle', '=', true],
        ];

        // Card Width
        $this->controls['checkboxCardWidth'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Label Width', 'bricks'),
            'type'  => 'number',
            'units' => true,
            'css'   => [
                [
                    'property' => 'width',
                    'selector' => '&[data-checkbox-custom] [data-field-type="checkbox"]:not([data-ignore-custom-styles]) .options-wrapper li label',
                ],
            ],
            'required' => [['checkboxCustomStyle', '=', true], ['checkboxCard', '=', true]],
        ];

        // Background Image
        $this->controls['checkboxBackground'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Checkbox Background', 'bricks'),
            'type'  => 'background',
            'css'   => [
                [
                    'property' => 'background',
                    'selector' => '&[data-checkbox-custom] .form-group:not([data-ignore-custom-styles]) input[type="checkbox"]',
                ],
            ],
            'required' => [['checkboxCustomStyle', '=', true], ['checkboxCard', '=', false]],
        ];

        // Border
        $this->controls['checkboxBorder'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Checkbox Border', 'bricks'),
            'type'  => 'border',
            'css'   => [
                [
                    'property' => 'border',
                    'selector' => '&[data-checkbox-custom] .form-group:not([data-ignore-custom-styles]) input[type="checkbox"]',
                ],
            ],
            'required' => [['checkboxCustomStyle', '=', true], ['checkboxCard', '=', false]],
        ];

        // Box Shadow
        $this->controls['checkboxBoxShadow'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Checkbox Box shadow', 'bricks'),
            'type'  => 'box-shadow',
            'css'   => [
                [
                    'property' => 'box-shadow',
                    'selector' => '&[data-checkbox-custom] .form-group:not([data-ignore-custom-styles]) input[type="checkbox"]',
                ],
            ],
            'required' => [['checkboxCustomStyle', '=', true], ['checkboxCard', '=', false]],
        ];

        // Padding
        $this->controls['checkboxPadding'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Checkbox Padding', 'bricks'),
            'type'  => 'spacing',
            'css'   => [
                [
                    'property' => 'padding',
                    'selector' => '&[data-checkbox-custom] .form-group:not([data-ignore-custom-styles]) input[type="checkbox"]',
                ],
            ],
            'required' => [['checkboxCustomStyle', '=', true], ['checkboxCard', '=', false]],
        ];

        // Checkbox Gap
        $this->controls['checkboxGap'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Checkbox Gap', 'bricks'),
            'type'  => 'number',
            'unit' => 'px',
            'units' => true,
            'css'   => [
                [
                    'property' => 'gap',
                    'selector' => '&[data-checkbox-custom] [data-field-type="checkbox"]:not([data-ignore-custom-styles]) .options-wrapper li',
                ],
            ],
            'required' => [['checkboxCustomStyle', '=', true], ['checkboxCard', '=', false]],
        ];

        // Card Background
        $this->controls['checkboxCardBackground'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Label Background', 'bricks'),
            'type'  => 'color',
            'css'   => [
                [
                    'property' => 'background-color',
                    'selector' => '&[data-checkbox-custom] [data-field-type="checkbox"]:not([data-ignore-custom-styles]) .options-wrapper li label',
                ],
            ],
            'required' => [['checkboxCustomStyle', '=', true]],
        ];

        // Card Checked Background
        $this->controls['checkboxCardCheckedBackground'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Label Background Checked', 'bricks'),
            'type'  => 'color',
            'css'   => [
                [
                    'property' => 'background-color',
                    'selector' => '&[data-checkbox-custom] [data-field-type="checkbox"]:not([data-ignore-custom-styles]) .options-wrapper li input:checked + label',
                ],
            ],
            'required' => [['checkboxCustomStyle', '=', true]],
        ];

        // Card Border
        $this->controls['checkboxCardBorder'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Label Border', 'bricks'),
            'type'  => 'border',
            'css'   => [
                [
                    'property' => 'border',
                    'selector' => '&[data-checkbox-custom] [data-field-type="checkbox"]:not([data-ignore-custom-styles]) .options-wrapper li label',
                ],
            ],
            'required' => [['checkboxCustomStyle', '=', true]],
        ];

        // Card Checked Border
        $this->controls['checkboxCardCheckedBorder'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Label Border Checked', 'bricks'),
            'type'  => 'border',
            'css'   => [
                [
                    'property' => 'border',
                    'selector' => '&[data-checkbox-custom] [data-field-type="checkbox"]:not([data-ignore-custom-styles]) .options-wrapper li input:checked + label',
                ],
            ],
            'required' => [['checkboxCustomStyle', '=', true]],
        ];

        // Card Box Shadow
        $this->controls['checkboxCardBoxShadow'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Label Box Shadow', 'bricks'),
            'type'  => 'box-shadow',
            'css'   => [
                [
                    'property' => 'box-shadow',
                    'selector' => '&[data-checkbox-custom] [data-field-type="checkbox"]:not([data-ignore-custom-styles]) .options-wrapper li label',
                ],
            ],
            'required' => [['checkboxCustomStyle', '=', true]],
        ];

        // Card Checked Box Shadow
        $this->controls['checkboxCardCheckedBoxShadow'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Label Box Shadow Checked', 'bricks'),
            'type'  => 'box-shadow',
            'css'   => [
                [
                    'property' => 'box-shadow',
                    'selector' => '&[data-checkbox-custom] [data-field-type="checkbox"]:not([data-ignore-custom-styles]) .options-wrapper li input:checked + label',
                ],
            ],
            'required' => [['checkboxCustomStyle', '=', true]],
        ];

        // Card Typography
        $this->controls['checkboxCardTypography'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Label Typography', 'bricks'),
            'type'  => 'typography',
            'css'   => [
                [
                    'property' => 'font',
                    'selector' => '&[data-checkbox-custom] [data-field-type="checkbox"]:not([data-ignore-custom-styles]) .options-wrapper li label',
                ],
            ],
            'required' => [['checkboxCustomStyle', '=', true]],
        ];

        // Card Checked Typography
        $this->controls['checkboxCardCheckedTypography'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Label Typography Checked', 'bricks'),
            'type'  => 'typography',
            'css'   => [
                [
                    'property' => 'font',
                    'selector' => '&[data-checkbox-custom] [data-field-type="checkbox"]:not([data-ignore-custom-styles]) .options-wrapper li input:checked + label',
                ],
            ],
            'required' => [['checkboxCustomStyle', '=', true]],
        ];

        // Card Transform
        $this->controls['checkboxCardTransform'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Label Transform', 'bricks'),
            'type'  => 'transform',
            'css'   => [
                [
                    'property' => 'transform',
                    'selector' => '&[data-checkbox-custom] [data-field-type="checkbox"]:not([data-ignore-custom-styles]) .options-wrapper li label',
                ],
            ],
            'required' => [['checkboxCustomStyle', '=', true]],
        ];

        // Card Checked Transform
        $this->controls['checkboxCardCheckedTransform'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Label Transform Checked', 'bricks'),
            'type'  => 'transform',
            'css'   => [
                [
                    'property' => 'transform',
                    'selector' => '&[data-checkbox-custom] [data-field-type="checkbox"]:not([data-ignore-custom-styles]) .options-wrapper li input:checked + label',
                ],
            ],
            'required' => [['checkboxCustomStyle', '=', true]],
        ];

        // Card Padding
        $this->controls['checkboxCardPadding'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Label Padding', 'bricks'),
            'type'  => 'spacing',
            'css'   => [
                [
                    'property' => 'padding',
                    'selector' => '&[data-checkbox-custom] [data-field-type="checkbox"]:not([data-ignore-custom-styles]) .options-wrapper li label',
                ],
            ],
            'required' => [['checkboxCustomStyle', '=', true]],
        ];

        // Card Transition
        $this->controls['checkboxCardTransition'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Label Transition', 'bricks'),
            'type'  => 'text',
            'css'   => [
                [
                    'property' => 'transition',
                    'selector' => '&[data-checkbox-custom] [data-field-type="checkbox"]:not([data-ignore-custom-styles]) .options-wrapper li label',
                ],
            ],
            'required' => [['checkboxCustomStyle', '=', true]],
        ];

        // Parent Flex Direction
        $this->controls['checkboxParentFlexDirection'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Parent Flex Direction', 'bricks'),
            'type'  => 'direction',
            'css'   => [
                [
                    'property' => 'flex-direction',
                    'selector' => '&[data-checkbox-custom] [data-field-type="checkbox"]:not([data-ignore-custom-styles]) .options-wrapper',
                ],
                [
                    'property' => 'display',
                    'value' => 'flex',
                    'selector' => '&[data-checkbox-custom] [data-field-type="checkbox"]:not([data-ignore-custom-styles]) .options-wrapper',
                ]
            ],
            'required' => ['checkboxCustomStyle', '=', true],
        ];

        // Parent Gap
        $this->controls['checkboxParentGap'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Parent Gap', 'bricks'),
            'type'  => 'number',
            'unit' => 'px',
            'units' => true,
            'css'   => [
                [
                    'property' => 'gap',
                    'selector' => '&[data-checkbox-custom] [data-field-type="checkbox"]:not([data-ignore-custom-styles]) .options-wrapper',
                ],
            ],
            'required' => ['checkboxCustomStyle', '=', true],
        ];


        // Separator: Radio Buttons
        $this->controls['radioSeparator'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Radio Buttons', 'bricks'),
            'type'  => 'separator',
        ];

        // Enable Custom Style
        $this->controls['radioCustomStyle'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Custom Style', 'bricks'),
            'type'  => 'checkbox',
            'default' => false,
            'rerender' => true,
            'description' => esc_html__('If checked, the radio buttons will be styled with CSS instead of the browser default.', 'bricks'),
            'css' => [
                [
                    'property' => 'appearance',
                    'value' => 'none',
                    'selector' => '&[data-radio-custom] .form-group:not([data-ignore-custom-styles]) input[type="radio"]',
                ],
                [
                    'property' => '-webkit-appearance',
                    'value' => 'none',
                    'selector' => '&[data-radio-custom].form-group:not([data-ignore-custom-styles]) input[type="radio"]',
                ],
                [
                    'property' => 'display',
                    'value' => 'flex',
                    'selector' => '&[data-radio-custom] [data-field-type="radio"]:not([data-ignore-custom-styles]) .options-wrapper li',
                ],
                [
                    'property' => 'flex-direction',
                    'value' => 'row',
                    'selector' => '&[data-radio-custom] [data-field-type="radio"]:not([data-ignore-custom-styles]) .options-wrapper li',
                ],
                [
                    'property' => 'align-items',
                    'value' => 'center',
                    'selector' => '&[data-radio-custom] [data-field-type="radio"]:not([data-ignore-custom-styles]) .options-wrapper li',
                ],
            ],
        ];

        // Width
        $this->controls['radioWidth'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Radio Width', 'bricks'),
            'type'  => 'number',
            'units' => true,
            'unit'  => 'px',
            'min'   => 0,
            'max'   => 100,
            'css'   => [
                [
                    'property' => 'width',
                    'selector' => '&[data-radio-custom] .form-group:not([data-ignore-custom-styles]) input[type="radio"]',
                ],
            ],
            'required' => [['radioCustomStyle', '=', true], ['radioCard', '=', false]],
        ];

        // Height
        $this->controls['radioHeight'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Radio Height', 'bricks'),
            'type'  => 'number',
            'unit' => 'px',
            'units' => true,
            'css'   => [
                [
                    'property' => 'height',
                    'selector' => '&[data-radio-custom] .form-group:not([data-ignore-custom-styles]) input[type="radio"]',
                ],
            ],
            'required' => [['radioCustomStyle', '=', true], ['radioCard', '=', false]],
        ];

        // Enable Card Style for Radio Buttons
        $this->controls['radioCard'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Is Card', 'bricks'),
            'type'  => 'checkbox',
            'default' => false,
            'rerender' => true,
            'required' => ['radioCustomStyle', '=', true],
        ];

        // Card Width
        $this->controls['radioCardWidth'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Label Width', 'bricks'),
            'type'  => 'number',
            'units' => true,
            'css'   => [
                [
                    'property' => 'width',
                    'selector' => '&[data-radio-custom] [data-field-type="radio"]:not([data-ignore-custom-styles]) .options-wrapper li label',
                ],
            ],
            'required' => [['radioCustomStyle', '=', true], ['radioCard', '=', true]],
        ];

        // Background Image
        $this->controls['radioBackground'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Radio Background', 'bricks'),
            'type'  => 'background',
            'css'   => [
                [
                    'property' => 'background',
                    'selector' => '&[data-radio-custom] .form-group:not([data-ignore-custom-styles]) input[type="radio"]',
                ],
            ],
            'required' => [['radioCustomStyle', '=', true], ['radioCard', '=', false]],
        ];

        // Border
        $this->controls['radioBorder'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Radio Border', 'bricks'),
            'type'  => 'border',
            'css'   => [
                [
                    'property' => 'border',
                    'selector' => '&[data-radio-custom] .form-group:not([data-ignore-custom-styles]) input[type="radio"]',
                ],
            ],
            'required' => [
                ['radioCustomStyle', '=', true], ['radioCard', '=', false]
            ],
        ];

        // Box Shadow
        $this->controls['radioBoxShadow'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Radio Box shadow', 'bricks'),
            'type'  => 'box-shadow',
            'css'   => [
                [
                    'property' => 'box-shadow',
                    'selector' => '&[data-radio-custom] .form-group:not([data-ignore-custom-styles]) input[type="radio"]',
                ],
            ],
            'required' => [['radioCustomStyle', '=', true], ['radioCard', '=', false]],
        ];

        // Padding
        $this->controls['radioPadding'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Radio Padding', 'bricks'),
            'type'  => 'spacing',
            'css'   => [
                [
                    'property' => 'padding',
                    'selector' => '&[data-radio-custom] .form-group:not([data-ignore-custom-styles]) input[type="radio"]',
                ],
            ],
            'required' => [['radioCustomStyle', '=', true], ['radioCard', '=', false]],
        ];

        // Radio Button Gap
        $this->controls['radioGap'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Radio Button Gap', 'bricks'),
            'type'  => 'number',
            'unit' => 'px',
            'units' => true,
            'css'   => [
                [
                    'property' => 'gap',
                    'selector' => '&[data-radio-custom] [data-field-type="radio"]:not([data-ignore-custom-styles]) .options-wrapper li',
                ],
            ],
            'required' => [['radioCustomStyle', '=', true], ['radioCard', '=', false]],
        ];

        // Card Background for Radio Buttons
        $this->controls['radioCardBackground'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Label Background', 'bricks'),
            'type'  => 'color',
            'css'   => [
                [
                    'property' => 'background-color',
                    'selector' => '&[data-radio-custom] [data-field-type="radio"]:not([data-ignore-custom-styles]) .options-wrapper li label',
                ],
            ],
            'required' => [['radioCustomStyle', '=', true]],
        ];

        // Card Checked Background for Radio Buttons
        $this->controls['radioCardCheckedBackground'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Label Background Checked', 'bricks'),
            'type'  => 'color',
            'css'   => [
                [
                    'property' => 'background-color',
                    'selector' => '&[data-radio-custom] [data-field-type="radio"]:not([data-ignore-custom-styles]) .options-wrapper li input:checked + label',
                ],
            ],
            'required' => [['radioCustomStyle', '=', true]],
        ];

        // Card Border for Radio Buttons
        $this->controls['radioCardBorder'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Label Border', 'bricks'),
            'type'  => 'border',
            'css'   => [
                [
                    'property' => 'border',
                    'selector' => '&[data-radio-custom] [data-field-type="radio"]:not([data-ignore-custom-styles]) .options-wrapper li label',
                ],
            ],
            'required' => [['radioCustomStyle', '=', true]],
        ];

        // Card Checked Border for Radio Buttons
        $this->controls['radioCardCheckedBorder'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Label Border Checked', 'bricks'),
            'type'  => 'border',
            'css'   => [
                [
                    'property' => 'border',
                    'selector' => '&[data-radio-custom] [data-field-type="radio"]:not([data-ignore-custom-styles]) .options-wrapper li input:checked + label',
                ],
            ],
            'required' => [['radioCustomStyle', '=', true]],
        ];

        // Card Box Shadow for Radio Buttons
        $this->controls['radioCardBoxShadow'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Label Box Shadow', 'bricks'),
            'type'  => 'box-shadow',
            'css'   => [
                [
                    'property' => 'box-shadow',
                    'selector' => '&[data-radio-custom] [data-field-type="radio"]:not([data-ignore-custom-styles]) .options-wrapper li label',
                ],
            ],
            'required' => [['radioCustomStyle', '=', true]],
        ];

        // Card Checked Box Shadow for Radio Buttons
        $this->controls['radioCardCheckedBoxShadow'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Label Box Shadow Checked', 'bricks'),
            'type'  => 'box-shadow',
            'css'   => [
                [
                    'property' => 'box-shadow',
                    'selector' => '&[data-radio-custom] [data-field-type="radio"]:not([data-ignore-custom-styles]) .options-wrapper li input:checked + label',
                ],
            ],
            'required' => [['radioCustomStyle', '=', true]],
        ];

        // Card Typography for Radio Buttons
        $this->controls['radioCardTypography'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Label Typography', 'bricks'),
            'type'  => 'typography',
            'css'   => [
                [
                    'property' => 'font',
                    'selector' => '&[data-radio-custom] [data-field-type="radio"]:not([data-ignore-custom-styles]) .options-wrapper li label',
                ],
            ],
            'required' => [['radioCustomStyle', '=', true]],
        ];

        // Card Checked Typography for Radio Buttons
        $this->controls['radioCardCheckedTypography'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Label Typography Checked', 'bricks'),
            'type'  => 'typography',
            'css'   => [
                [
                    'property' => 'font',
                    'selector' => '&[data-radio-custom] [data-field-type="radio"]:not([data-ignore-custom-styles]) .options-wrapper li input:checked + label',
                ],
            ],
            'required' => [['radioCustomStyle', '=', true]],
        ];

        // Card Transform for Radio Buttons
        $this->controls['radioCardTransform'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Label Transform', 'bricks'),
            'type'  => 'transform',
            'css'   => [
                [
                    'property' => 'transform',
                    'selector' => '&[data-radio-custom] [data-field-type="radio"]:not([data-ignore-custom-styles]) .options-wrapper li label',
                ],
            ],
            'required' => [['radioCustomStyle', '=', true]],
        ];

        // Card Checked Transform for Radio Buttons
        $this->controls['radioCardCheckedTransform'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Label Transform Checked', 'bricks'),
            'type'  => 'transform',
            'css'   => [
                [
                    'property' => 'transform',
                    'selector' => '&[data-radio-custom] [data-field-type="radio"]:not([data-ignore-custom-styles]) .options-wrapper li input:checked + label',
                ],
            ],
            'required' => [['radioCustomStyle', '=', true]],
        ];

        // Card Padding
        $this->controls['radioCardPadding'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Label Padding', 'bricks'),
            'type'  => 'spacing',
            'css'   => [
                [
                    'property' => 'padding',
                    'selector' => '&[data-radio-custom] [data-field-type="radio"]:not([data-ignore-custom-styles]) .options-wrapper li label',
                ],
            ],
            'required' => [['radioCustomStyle', '=', true]],
        ];

        // Parent Flex Direction
        $this->controls['radioParentFlexDirection'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Parent Flex Direction', 'bricks'),
            'type'  => 'direction',
            'css'   => [
                [
                    'property' => 'flex-direction',
                    'selector' => '&[data-radio-custom] [data-field-type="radio"]:not([data-ignore-custom-styles]) .options-wrapper',
                ],
                [
                    'property' => 'display',
                    'value' => 'flex',
                    'selector' => '&[data-radio-custom] [data-field-type="radio"]:not([data-ignore-custom-styles]) .options-wrapper',
                ],
            ],
            'required' => ['radioCustomStyle', '=', true],
        ];

        // Parent Gap
        $this->controls['radioParentGap'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Parent Gap', 'bricks'),
            'type'  => 'number',
            'unit' => 'px',
            'units' => true,
            'css'   => [
                [
                    'property' => 'gap',
                    'selector' => '&[data-radio-custom] [data-field-type="radio"]:not([data-ignore-custom-styles]) .options-wrapper',
                ],
            ],
            'required' => ['radioCustomStyle', '=', true],
        ];

        // Separator: Group

        $this->controls['groupSeparator'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Group', 'bricks'),
            'type'  => 'separator',
        ];

        // Group Background
        $this->controls['groupBackgroundColor'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Background color', 'bricks'),
            'type'  => 'color',
            'css'   => [
                [
                    'property' => 'background-color',
                    'selector' => '.is-group',
                ],
            ],
        ];

        // Group Padding
        $this->controls['groupPadding'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Padding', 'bricks'),
            'type'  => 'spacing',
            'css'   => [
                [
                    'property' => 'padding',
                    'selector' => '.is-group',
                ],
            ],
        ];

        // Group Margin
        $this->controls['groupMargin'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Margin', 'bricks'),
            'type'  => 'spacing',
            'css'   => [
                [
                    'property' => 'margin',
                    'selector' => '.is-group',
                ],
            ],
        ];

        // Group Alignment
        $this->controls['groupAlignment'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Alignment', 'bricks'),
            'type'  => 'justify-content',
            'css'   => [
                [
                    'property' => 'justify-content',
                    'selector' => '.is-group',
                ],
            ],
        ];

        // Group Border
        $this->controls['groupBorder'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Border', 'bricks'),
            'type'  => 'border',
            'css'   => [
                [
                    'property' => 'border',
                    'selector' => '.is-group',
                ],
            ],
        ];

        // Group Box Shadow
        $this->controls['groupBoxShadow'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Box shadow', 'bricks'),
            'type'  => 'box-shadow',
            'css'   => [
                [
                    'property' => 'box-shadow',
                    'selector' => '.is-group',
                ],
            ],
        ];

        // Group Typography
        $this->controls['groupTypography'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Typography', 'bricks'),
            'type'  => 'typography',
            'css'   => [
                [
                    'property' => 'font',
                    'selector' => '.is-group',
                ],
            ],
        ];

        // Group Heading Typography
        $this->controls['groupHeadingTypography'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Heading typography', 'bricks'),
            'type'  => 'typography',
            'css'   => [
                [
                    'property' => 'font',
                    'selector' => '.is-group .form-group h1',
                ],
                [
                    'property' => 'font',
                    'selector' => '.is-group .form-group h2',
                ],
                [
                    'property' => 'font',
                    'selector' => '.is-group .form-group h3',
                ],
                [
                    'property' => 'font',
                    'selector' => '.is-group .form-group h4',
                ],
                [
                    'property' => 'font',
                    'selector' => '.is-group .form-group h5',
                ],
                [
                    'property' => 'font',
                    'selector' => '.is-group .form-group h6',
                ],
            ],
        ];

        // Group Heading Margin
        $this->controls['groupHeadingMargin'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Heading margin', 'bricks'),
            'type'  => 'spacing',
            'css'   => [
                [
                    'property' => 'margin',
                    'selector' => '.is-group .brf-field-heading-wrapper',
                ],
            ],
        ];

        // Group Field Background Color
        $this->controls['groupFieldBackgroundColor'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Field background color', 'bricks'),
            'type'  => 'color',
            'css'   => [
                [
                    'property' => 'background-color',
                    'selector' => '.is-group .form-group input',
                ],
                [
                    'property' => 'background-color',
                    'selector' => '.is-group .flatpickr',
                ],
                [
                    'property' => 'background-color',
                    'selector' => '.is-group select',
                ],
                [
                    'property' => 'background-color',
                    'selector' => '.is-group textarea',
                ],
            ],
        ];

        // Group Field Border
        $this->controls['groupFieldBorder'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Field border', 'bricks'),
            'type'  => 'border',
            'css'   => [
                [
                    'property' => 'border',
                    'selector' => '.is-group .form-group input',
                ],
                [
                    'property' => 'border',
                    'selector' => '.is-group .flatpickr',
                ],
                [
                    'property' => 'border',
                    'selector' => '.is-group select',
                ],
                [
                    'property' => 'border',
                    'selector' => '.is-group textarea',
                ],
            ],
        ];

        // Group Field Box Shadow
        $this->controls['groupFieldBoxShadow'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Field box shadow', 'bricks'),
            'type'  => 'box-shadow',
            'css'   => [
                [
                    'property' => 'box-shadow',
                    'selector' => '.is-group .form-group input',
                ],
                [
                    'property' => 'box-shadow',
                    'selector' => '.is-group .flatpickr',
                ],
                [
                    'property' => 'box-shadow',
                    'selector' => '.is-group select',
                ],
                [
                    'property' => 'box-shadow',
                    'selector' => '.is-group textarea',
                ],
            ],
        ];

        // Group Field Typography
        $this->controls['groupFieldTypography'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Field typography', 'bricks'),
            'type'  => 'typography',
            'css'   => [
                [
                    'property' => 'font',
                    'selector' => '.is-group .form-group input',
                ],
                [
                    'property' => 'font',
                    'selector' => '.is-group select',
                ],
                [
                    'property' => 'font',
                    'selector' => '.is-group textarea',
                ],
            ],
        ];

        // Group Field Placeholder Typography
        $this->controls['groupFieldPlaceholderTypography'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Field placeholder typography', 'bricks'),
            'type'  => 'typography',
            'css'   => [
                [
                    'property' => 'font',
                    'selector' => '.is-group .form-group input::placeholder',
                ],
                [
                    'property' => 'font',
                    'selector' => '.is-group .form-group select',
                ],
                [
                    'property' => 'font',
                    'selector' => '.is-group .form-group textarea',
                ],
            ],
        ];

        // Group Field Margin
        $this->controls['groupFieldMargin'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Field margin', 'bricks'),
            'type'  => 'spacing',
            'css'   => [
                [
                    'property' => 'padding',
                    'selector' => '.is-group .form-group',
                ],
            ],
        ];

        // Group Field Padding
        $this->controls['groupFieldPadding'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Field padding', 'bricks'),
            'type'  => 'spacing',
            'css'   => [
                [
                    'property' => 'padding',
                    'selector' => '.is-group .form-group input',
                ],
                [
                    'property' => 'padding',
                    'selector' => '.is-group .flatpickr',
                ],
                [
                    'property' => 'padding',
                    'selector' => '.is-group select',
                ],
                [
                    'property' => 'padding',
                    'selector' => '.is-group textarea',
                ],
            ],
        ];

        // Separator: Icon

        $this->controls['iconSeparator'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Icon', 'bricks'),
            'type'  => 'separator',
        ];

        // Gap
        $this->controls['iconGap'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Gap', 'bricks'),
            'type'  => 'number',
            'css'   => [
                [
                    'property' => 'gap',
                    'selector' => '.input-icon-wrapper',
                ],
            ],
            'required' => ['iconInset', '!=', true],
        ];

        // Position
        $this->controls['iconPosition'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Position', 'bricks'),
            'type'  => 'select',
            'default' => 'row',
            'options' => [
                'row' => esc_html__('Left', 'bricks'),
                'row-reverse' => esc_html__('Right', 'bricks'),
            ],
            'css'   => [
                [
                    'property' => 'flex-direction',
                    'selector' => '.input-icon-wrapper',
                ],
            ],
            'rerender' => true
        ];

        // Inset
        $this->controls['iconInset'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Inset', 'bricks'),
            'type'  => 'checkbox',
        ];

        // Icon Offset Left (Inset)
        $this->controls['iconOffsetLeft'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Offset Left (Inset)', 'bricks'),
            'type'  => 'number',
            'required' => ['iconInset', '=', true],
            'css'   => [
                [
                    'property' => 'left',
                    'selector' => '.icon-inset.icon-left .input-icon',
                ],
            ],
        ];

        // Icon Offset Right (Inset)
        $this->controls['iconOffsetRight'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Offset Right (Inset)', 'bricks'),
            'type'  => 'number',
            'required' => ['iconInset', '=', true],
            'css'   => [
                [
                    'property' => 'right',
                    'selector' => '.icon-inset.icon-right .input-icon',
                ],
            ],
        ];

        // Input Padding Left (Inset)
        $this->controls['iconInputPaddingLeft'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Input Padding Left (Inset)', 'bricks'),
            'type'  => 'number',
            'required' => ['iconInset', '=', true],
            'css'   => [
                [
                    'property' => 'padding-left',
                    'selector' => '.icon-inset input',
                ],
            ],
        ];

        // Input Padding Right (Inset)
        $this->controls['iconInputPaddingRight'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Input Padding Right (Inset)', 'bricks'),
            'type'  => 'number',
            'required' => ['iconInset', '=', true],
            'css'   => [
                [
                    'property' => 'padding-right',
                    'selector' => '.icon-inset input',
                ],
            ],
        ];

        // Icon Size
        $this->controls['iconSize'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Size', 'bricks'),
            'type'  => 'number',
            'css'   => [
                [
                    'property' => 'font-size',
                    'selector' => '.input-icon',
                ],
                [
                    'property' => 'width',
                    'selector' => '.input-icon svg',
                ],
            ],
        ];

        // Icon Width
        $this->controls['iconWidth'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Width', 'bricks'),
            'type'  => 'number',
            'css'   => [
                [
                    'property' => 'width',
                    'selector' => '.input-icon',
                ],
            ],
        ];

        // Icon Padding
        $this->controls['iconPadding'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Padding', 'bricks'),
            'type'  => 'dimensions',
            'css'   => [
                [
                    'property' => 'padding',
                    'selector' => '.input-icon',
                ],
            ],
        ];

        // Icon Margin
        $this->controls['iconMargin'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Margin', 'bricks'),
            'type'  => 'dimensions',
            'css'   => [
                [
                    'property' => 'margin',
                    'selector' => '.input-icon',
                ],
            ],
        ];

        // Icon Transform
        $this->controls['iconTransform'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Transform', 'bricks'),
            'type'  => 'transform',
            'css'   => [
                [
                    'property' => 'transform',
                    'selector' => '.input-icon',
                ],
            ],
        ];

        // Icon Background Color
        $this->controls['iconBackgroundColor'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Background Color', 'bricks'),
            'type'  => 'color',
            'css'   => [
                [
                    'property' => 'background-color',
                    'selector' => '.input-icon',
                ],
            ],
        ];

        // Icon Color
        $this->controls['iconColor'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Color', 'bricks'),
            'type'  => 'color',
            'css'   => [
                [
                    'property' => 'color',
                    'selector' => '.input-icon',
                ],
                [
                    'property' => 'fill',
                    'selector' => '.input-icon svg',
                ],
                [
                    'property' => 'fill',
                    'selector' => '.input-icon svg path',
                ],
            ],
        ];

        // Icon Border
        $this->controls['iconBorder'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Border', 'bricks'),
            'type'  => 'border',
            'css'   => [
                [
                    'property' => 'border',
                    'selector' => '.input-icon',
                ],
            ],
        ];

        // Icon Box Shadow
        $this->controls['iconBoxShadow'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Box Shadow', 'bricks'),
            'type'  => 'box-shadow',
            'css'   => [
                [
                    'property' => 'box-shadow',
                    'selector' => '.input-icon',
                ],
            ],
        ];

        // Focus Input On Click
        $this->controls['iconFocusInput'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Focus Input On Click', 'bricks'),
            'type'  => 'checkbox',
            'default' => true,
        ];

        // Separator: Heading

        $this->controls['headingSeparator'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Heading', 'bricks'),
            'type'  => 'separator',
        ];

        // Heading Wrapper Margin

        $this->controls['headingWrapperMargin'] = [
            'tab'         => 'content',
            'group'       => 'fields',
            'label'       => esc_html__('Wrapper Margin', 'bricks'),
            'type'        => 'spacing',
            'css'         => [
                [
                    'property' => 'margin',
                    'selector' => '.brf-field-heading-wrapper',
                ],
            ],
            'placeholder' => [
                'top'    => 0,
                'right'  => 0,
                'bottom' => '15px',
                'left'   => 0,
            ],
        ];

        // Heading Margin

        $this->controls['headingMargin'] = [
            'tab'         => 'content',
            'group'       => 'fields',
            'label'       => esc_html__('Heading Margin', 'bricks'),
            'type'        => 'spacing',
            'css'         => [
                [
                    'property' => 'margin',
                    'selector' => '.brf-field-heading',
                ],
            ],
            'placeholder' => [
                'top'    => 0,
                'right'  => 0,
                'bottom' => 0,
                'left'   => 0,
            ],
        ];

        // Heading Description Margin

        $this->controls['headingDescriptionMargin'] = [
            'tab'         => 'content',
            'group'       => 'fields',
            'label'       => esc_html__('Description Margin', 'bricks'),
            'type'        => 'spacing',
            'css'         => [
                [
                    'property' => 'margin',
                    'selector' => '.brf-field-heading-description',
                ],
            ],
            'placeholder' => [
                'top'    => 0,
                'right'  => 0,
                'bottom' => 0,
                'left'   => 0,
            ],
        ];

        $this->controls['headingTypography'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Heading Typography', 'bricks'),
            'type'  => 'typography',
            'css'   => [
                [
                    'property' => 'font',
                    'selector' => '.brf-field-heading',
                ],
            ],
        ];

        $this->controls['headingDescriptionTypography'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Heading Description Typography', 'bricks'),
            'type'  => 'typography',
            'css'   => [
                [
                    'property' => 'font',
                    'selector' => '.brf-field-heading-description',
                ],
            ],
        ];

        // Separator: Divider

        $this->controls['dividerSeparator'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Divider', 'bricks'),
            'type'  => 'separator',
        ];

        // Divider Margin

        $this->controls['dividerMargin'] = [
            'tab'         => 'content',
            'group'       => 'fields',
            'label'       => esc_html__('Margin', 'bricks'),
            'type'        => 'spacing',
            'css'         => [
                [
                    'property' => 'margin',
                    'selector' => '.brf-field-divider',
                ],
            ],
            'placeholder' => [
                'top'    => '15px',
                'right'  => 0,
                'bottom' => '15px',
                'left'   => 0,
            ],
        ];

        // Divider Border

        $this->controls['dividerBorder'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Border', 'bricks'),
            'type'  => 'border',
            'css'   => [
                [
                    'property' => 'border',
                    'selector' => '.brf-field-divider',
                ],
            ],
        ];

        // Divider Width

        $this->controls['dividerWidth'] = [
            'tab'         => 'content',
            'group'       => 'fields',
            'label'       => esc_html__('Width', 'bricks'),
            'type'        => 'number',
            'units' => [
                '%' => [
                    'min' => 0,
                    'max' => 100,
                ],
                'px' => [
                    'min' => 0,
                    'max' => 1000,
                ],
            ],
            'default' => '100%',
            'css'         => [
                [
                    'property' => 'width',
                    'selector' => '.brf-field-divider',
                ],
            ],
        ];

        // Rich Text Separator
        $this->controls['richTextSeparator'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Rich Text', 'bricks'),
            'type'  => 'separator',
        ];

        // Info
        $this->controls['richTextInfo'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'content' => esc_html__('If you use the style "WordPress", the styling possibilities are limited.', 'bricks'),
            'type'  => 'info',
        ];

        // Rich Text Border
        $this->controls['richTextBorder'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Border', 'bricks'),
            'type'  => 'border',
            'css'   => [
                [
                    'property' => 'border',
                    'selector' => '.ql-toolbar',
                ],
                [
                    'property' => 'border',
                    'selector' => '.ql-container',
                ],
                [
                    'property' => 'border',
                    'selector' => '.mce-edit-area',
                    'important' => true
                ],

            ],
        ];

        // Rich Text Typography
        $this->controls['richTextTypography'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Typography', 'bricks'),
            'type'  => 'typography',
            'css'   => [
                [
                    'property' => 'font',
                    'selector' => '.ql-editor',
                ]
            ],
        ];

        // Rich Text Toolbar Background

        $this->controls['richTextToolbarBackground'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Toolbar Background', 'bricks'),
            'type'  => 'color',
            'css'   => [
                [
                    'property' => 'background-color',
                    'selector' => '.ql-toolbar',
                ],
                [
                    'property' => 'background-color',
                    'selector' => '.ql-toolbar .ql-picker-options',
                ],
            ],
        ];

        // Toolbar SVG Background Color

        $this->controls['richTextToolbarSvgBackgroundColor'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Toolbar Icon Background Color', 'bricks'),
            'type'  => 'color',
            'css'   => [
                [
                    'property' => 'background-color',
                    'selector' => '.ql-formats button, .ql-formats .ql-picker',
                ]
            ],
        ];


        // Rich Text Toolbar SVG Color

        $this->controls['richTextToolbarSvgColor'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Toolbar Icon Color', 'bricks'),
            'type'  => 'color',
            'css'   => [
                [
                    'property' => 'stroke',
                    'selector' => '.ql-toolbar svg',
                ],
                [
                    'property' => 'stroke',
                    'selector' => '.ql-toolbar svg path',
                ],
                [
                    'property' => 'stroke',
                    'selector' => '.ql-toolbar svg line',
                ],
                [
                    'property' => 'stroke',
                    'selector' => '.ql-toolbar svg circle',
                ],
                [
                    'property' => 'stroke',
                    'selector' => '.ql-toolbar svg polygon',
                ],
                [
                    'property' => 'stroke',
                    'selector' => '.ql-toolbar svg rect',
                ],
                [
                    'property' => 'stroke',
                    'selector' => '.ql-toolbar svg polyline',
                ],
                [
                    'property' => 'fill',
                    'selector' => '.ql-toolbar svg polyline',
                ],
                [
                    'property' => 'color',
                    'selector' => '.ql-toolbar span',
                ],
                [
                    'property' => 'fill',
                    'selector' => '.ql-toolbar .ql-strike svg path',
                    'value' => 'transparent'
                ],
                [
                    'property' => 'fill',
                    'selector' => '.ql-toolbar .ql-video svg rect',
                    'value' => 'transparent'
                ],
                [
                    'property' => 'fill',
                    'selector' => '.ql-toolbar .ql-color-picker svg polyline',
                    'value' => 'transparent'
                ],
                [
                    'property' => 'fill',
                    'selector' => '.ql-toolbar .ql-direction svg path',
                    'value' => 'transparent'
                ],
                [
                    'property' => 'fill',
                    'selector' => '.ql-toolbar .ql-image svg circle',
                    'value' => 'transparent'
                ],
                [
                    'property' => 'fill',
                    'selector' => '.ql-toolbar .ql-blockquote svg rect',
                    'value' => 'transparent'
                ],
            ],

        ];

        // Rich Text Editor Background

        $this->controls['richTextEditorBackground'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Editor Background', 'bricks'),
            'type'  => 'color',
            'css'   => [
                [
                    'property' => 'background-color',
                    'selector' => '.ql-container',
                ],
            ],
        ];

        // Rich Text Editor Color

        $this->controls['richTextEditorColor'] = [
            'tab'   => 'content',
            'group' => 'fields',
            'label' => esc_html__('Editor Color', 'bricks'),
            'type'  => 'color',
            'css'   => [
                [
                    'property' => 'color',
                    'selector' => '.ql-editor',
                ],
                // Placeholders
                [
                    'property' => 'color',
                    'selector' => '.ql-editor::before',
                ],
                [
                    'property' => 'color',
                    'selector' => '.ql-editor::after',
                ],
            ],
        ];

        // Group: Submit Button

        $this->controls['submitButtonClass'] = [
            'tab'         => 'content',
            'group'       => 'submitButton',
            'label'       => esc_html__('CSS Class', 'bricks'),
            'type'        => 'text',
            'inline'      => true,
        ];

        $this->controls['submitButtonText'] = [
            'tab'         => 'content',
            'group'       => 'submitButton',
            'label'       => esc_html__('Text', 'bricks'),
            'type'        => 'text',
            'inline'      => true,
            'placeholder' => esc_html__('Send', 'bricks'),
        ];

        $this->controls['submitButtonSize'] = [
            'tab'     => 'content',
            'group'   => 'submitButton',
            'label'   => esc_html__('Size', 'bricks'),
            'type'    => 'select',
            'inline'  => true,
            'options' => $this->control_options['buttonSizes'],
        ];

        $this->controls['submitButtonStyle'] = [
            'tab'         => 'content',
            'group'       => 'submitButton',
            'label'       => esc_html__('Style', 'bricks'),
            'type'        => 'select',
            'inline'      => true,
            'options'     => $this->control_options['styles'],
            'default'     => 'primary',
            'placeholder' => esc_html__('Custom', 'bricks'),
        ];

        $this->controls['submitButtonWidth'] = [
            'tab'   => 'content',
            'group' => 'submitButton',
            'label' => esc_html__('Width', 'bricks') . ' (%)',
            'type'  => 'number',
            'unit'  => '%',
            'css'   => [
                [
                    'property' => 'width',
                    'selector' => '.submit-button-wrapper',
                ],
            ],
        ];

        $this->controls['submitButtonMargin'] = [
            'tab'   => 'content',
            'group' => 'submitButton',
            'label' => esc_html__('Margin', 'bricks'),
            'type'  => 'spacing',
            'css'   => [
                [
                    'property' => 'margin',
                    'selector' => '.submit-button-wrapper',
                ],
            ],
        ];

        $this->controls['submitButtonTypography'] = [
            'tab'   => 'content',
            'group' => 'submitButton',
            'label' => esc_html__('Typography', 'bricks'),
            'type'  => 'typography',
            'css'   => [
                [
                    'property' => 'font',
                    'selector' => '.bricks-button',
                ]
            ],
        ];

        $this->controls['submitButtonBackgroundColor'] = [
            'tab'   => 'content',
            'group' => 'submitButton',
            'label' => esc_html__('Background', 'bricks'),
            'type'  => 'color',
            'css'   => [
                [
                    'property' => 'background-color',
                    'selector' => '.bricks-button',
                ]
            ],
        ];

        $this->controls['submitButtonBorder'] = [
            'tab'   => 'content',
            'group' => 'submitButton',
            'label' => esc_html__('Border', 'bricks'),
            'type'  => 'border',
            'css'   => [
                [
                    'property' => 'border',
                    'selector' => 'button[type=submit].bricks-button',
                ],
            ],
        ];

        $this->controls['submitButtonIcon'] = [
            'tab'   => 'content',
            'group' => 'submitButton',
            'label' => esc_html__('Icon', 'bricks'),
            'type'  => 'icon',
        ];

        $this->controls['submitButtonIconPosition'] = [
            'tab'         => 'content',
            'group'       => 'submitButton',
            'label'       => esc_html__('Icon position', 'bricks'),
            'type'        => 'select',
            'options'     => $this->control_options['iconPosition'],
            'inline'      => true,
            'placeholder' => esc_html__('Right', 'bricks'),
            'required'    => ['submitButtonIcon', '!=', ''],
        ];

        $this->controls['submitButtonHasCondition'] = [
            'tab'     => 'content',
            'group'   => 'submitButton',
            'label'   => esc_html__('Submit Conditions', 'bricks'),
            'type'    => 'checkbox',
            'default' => false
        ];

        $this->controls['submitButtonConditions'] = [
            'required'      => ['submitButtonHasCondition', '=', true],
            'tab'           => 'content',
            'label'         => 'If',
            'group'         => 'submitButton',
            'type'          => 'repeater',
            'titleProperty' => 'submitButtonCondition',
            'fields'        => [
                'submitButtonConditionPostId'   => [
                    'label'       => esc_html__('Post ID', 'bricks'),
                    'type'        => 'text',
                    'placeholder' => 'Leave Empty For Current Post ID',
                    'required'    => [['submitButtonCondition', '=', 'post_meta']],
                ],
                'submitButtonCondition'         => [
                    'tab'     => 'content',
                    'group'   => 'submitButton',
                    'type'    => 'select',
                    'options' => $this->get_submit_conditions(),
                    'default' => 'option'
                ],

                'submitButtonConditionValue'    => [
                    'required' => [['submitButtonCondition'], ['submitButtonCondition', '!=', 'submission_count_reached']],
                    'tab'      => 'content',
                    'group'    => 'submitButton',
                    'type'     => 'text',
                    'default'  => ''
                ],

                'submitButtonConditionOperator' => [
                    'required' => [['submitButtonConditionValue'], ['submitButtonCondition', '!=', 'submission_count_reached']],
                    'tab'      => 'content',
                    'group'    => 'submitButton',
                    'type'     => 'select',
                    'options'  => $this->get_condition_operators(),
                    'default'  => '=='
                ],

                'submitButtonConditionValue2'   => [
                    'required' => [['submitButtonConditionOperator'], ['submitButtonConditionValue', '!=', ''], ['submitButtonCondition', '!=', 'submission_count_reached'], ['submitButtonConditionOperator', '!=', ['exists', 'not_exists', 'empty', 'not_empty']]],
                    'tab'      => 'content',
                    'group'    => 'submitButton',
                    'type'     => 'text',
                    'default'  => ''
                ],

                'submitButtonConditionType'     => [
                    'required' => [['submitButtonConditionValue2'], ['submitButtonCondition', '!=', 'submission_count_reached']],
                    'tab'      => 'content',
                    'group'    => 'submitButton',
                    'label'    => esc_html__('Data Type', 'bricks'),
                    'type'     => 'select',
                    'options'  => $this->get_condition_data_types(),
                    'default'  => 'string'
                ]
            ]
        ];

        $this->controls['submitButtonConditionAction'] = [
            'required' => ['submitButtonHasCondition', '=', true],
            'tab'      => 'content',
            'group'    => 'submitButton',
            'label'    => esc_html__('Then', 'bricks'),
            'type'     => 'select',
            'options'  => [
                'hide'     => esc_html__('Hide Submit Button', 'bricks'),
                'disabled' => esc_html__('Disable Submit Button', 'bricks'),
            ],
            'default'  => 'disabled',
        ];

        // Add Select "Relation" And and Or
        $this->controls['submitButtonConditionsRelation'] = [
            'required' => ['submitButtonHasCondition', '=', true],
            'tab'      => 'content',
            'group'    => 'submitButton',
            'label'    => esc_html__('Conditions Relation', 'bricks'),
            'type'     => 'select',
            'options'  => [
                'and' => esc_html__('AND', 'bricks'),
                'or'  => esc_html__('OR', 'bricks'),
            ],
            'default'  => 'and'
        ];

        $this->controls['submitButtonConditionsAlternativeText'] = [
            'required'    => ['submitButtonHasCondition', '=', true],
            'tab'         => 'content',
            'group'       => 'submitButton',
            'label'       => esc_html__('Alternative Button Text', 'bricks'),
            'type'        => 'text',
            'description' => esc_html__('If you want to show a different text on the submit button when the conditions are not met, enter it here.', 'bricks'),
        ];

        // Group: Actions

        $this->controls['actions'] = [
            'tab'         => 'content',
            'group'       => 'actions',
            'type'        => 'select',
            'label'       => esc_html__('Actions after successful form submit', 'bricks'),
            'placeholder' => esc_html__('None', 'bricks'),
            'options'     => $this->get_actions(),
            'multiple'    => true,
            'description' => esc_html__('Select action(s) you want to perform after form has been successfully submitted.', 'bricks'),
            'default'     => ['email'],
        ];

        $this->controls['info'] = [
            'tab'      => 'content',
            'group'    => 'actions',
            'content'  => esc_html__('You did not select any action(s). So when this form it submitted nothing happens.', 'bricks'),
            'type'     => 'info',
            'required' => ['actions', '=', ''],
        ];

        $this->controls['successMessage'] = [
            'tab'     => 'content',
            'group'   => 'actions',
            'label'   => esc_html__('Success message', 'bricks'),
            'type'    => 'text',
            'default' => esc_html__('Message successfully sent. We will get back to you as soon as possible.', 'bricks'),
        ];

        // Group: Email

        $this->controls['emailInfo'] = [
            'tab'     => 'content',
            'group'   => 'email',
            'type'    => 'info',
            'content' => esc_html__('Use any form field value via it\'s ID like this: {{form_field}}. Replace "form_field" with the actual field ID.', 'bricks'),
        ];

        $this->controls['emailSubject'] = [
            'tab'     => 'content',
            'group'   => 'email',
            'label'   => esc_html__('Subject', 'bricks'),
            'type'    => 'text',
            'default' => 'Contact form request',
        ];

        $this->controls['emailTo'] = [
            'tab'       => 'content',
            'group'     => 'email',
            'label'     => esc_html__('Send to email address', 'bricks'),
            'type'      => 'select',
            'options'   => [
                'admin_email' => sprintf('%s (' . get_option('admin_email') . ')', esc_html__('Admin email', 'bricks')),
                'custom'      => esc_html__('Custom email address', 'bricks'),
            ],
            'default'   => 'admin_email',
            'clearable' => false,
        ];

        $this->controls['emailToCustom'] = [
            'tab'         => 'content',
            'group'       => 'email',
            'label'       => esc_html__('Send to custom email address', 'bricks'),
            'description' => esc_html__('Accepts multiple addresses separated by comma', 'bricks'),
            'type'        => 'text',
            'required'    => ['emailTo', '=', 'custom'],
        ];

        $this->controls['emailBcc'] = [
            'tab'   => 'content',
            'group' => 'email',
            'label' => esc_html__('BCC email address', 'bricks'),
            'type'  => 'text',
        ];

        $this->controls['fromEmail'] = [
            'tab'   => 'content',
            'group' => 'email',
            'label' => esc_html__('From email address', 'bricks'),
            'type'  => 'text',
        ];

        $this->controls['fromName'] = [
            'tab'     => 'content',
            'group'   => 'email',
            'label'   => esc_html__('From name', 'bricks'),
            'type'    => 'text',
            'default' => get_option('blogname'),
        ];

        $this->controls['replyToEmail'] = [
            'tab'         => 'content',
            'group'       => 'email',
            'label'       => esc_html__('Reply to email address', 'bricks'),
            'type'        => 'text',
            'description' => esc_html__('Default: Email submitted via form.', 'bricks'),
        ];

        $this->controls['emailContent'] = [
            'tab'         => 'content',
            'group'       => 'email',
            'label'       => esc_html__('Email content', 'bricks'),
            'type'        => 'textarea',
            'description' => __('<button onclick="BrfProForms.handleDefaultEmailContent(true, true)" class="button" style="text-decoration: none;margin-top: -2px">Refresh Email Content</button>', 'bricks'),
        ];

        $this->controls['refreshEmailContent'] = [
            'tab'         => 'content',
            'group'       => 'email',
            'label'       => esc_html__('Live Refresh Email Content in Builder', 'bricks'),
            'type'        => 'checkbox',
            'description' => esc_html__('Refresh Email Content in Builder automatically. If changing or adding fields, they will be automatically added in the email content.', 'bricks'),
        ];

        // $this->controls['emailSuccessMessage'] = [
        // 'tab' => 'content',
        // 'group' => 'email',
        // 'label' => esc_html__( 'Success message', 'bricks' ),
        // 'type' => 'text',
        // 'default' => esc_html__( 'Message successfully sent. We will get back to you as soon as possible.', 'bricks' ),
        // ];

        $this->controls['emailErrorMessage'] = [
            'tab'     => 'content',
            'group'   => 'email',
            'label'   => esc_html__('Error message', 'bricks'),
            'type'    => 'text',
            'default' => esc_html__('Submission failed. Please reload the page and try to submit the form again.', 'bricks'),
        ];

        $this->controls['htmlEmail'] = [
            'tab'     => 'content',
            'group'   => 'email',
            'label'   => esc_html__('HTML email', 'bricks'),
            'type'    => 'checkbox',
            'default' => true,
        ];

        // Group: Confirmation email (@since 1.7.2)

        $this->controls['confirmationEmailDescription'] = [
            'tab'     => 'content',
            'group'   => 'confirmation',
            'type'    => 'info',
            'content' => Helpers::article_link('form/#confirmation-email', esc_html__('Please ensure SMTP is set up on this site so all outgoing emails are delivered properly.', 'bricks')),
        ];

        $this->controls['confirmationEmailSubject'] = [
            'tab'   => 'content',
            'group' => 'confirmation',
            'label' => esc_html__('Subject', 'bricks'),
            'type'  => 'text',
        ];

        $this->controls['confirmationEmailTo'] = [
            'tab'         => 'content',
            'group'       => 'confirmation',
            'label'       => esc_html__('Send to email address', 'bricks'),
            'type'        => 'text',
            'description' => esc_html__('Default', 'bricks') . ': ' . esc_html__('Email address in submitted form', 'bricks'),
        ];

        $this->controls['confirmationFromEmail'] = [
            'tab'         => 'content',
            'group'       => 'confirmation',
            'label'       => esc_html__('From email address', 'bricks'),
            'type'        => 'text',
            'description' => esc_html__('Default', 'bricks') . ': ' . esc_html__('Admin email', 'bricks'),
        ];

        $this->controls['confirmationFromName'] = [
            'tab'   => 'content',
            'group' => 'confirmation',
            'label' => esc_html__('From name', 'bricks'),
            'type'  => 'text',
        ];

        $this->controls['confirmationEmailContent'] = [
            'tab'         => 'content',
            'group'       => 'confirmation',
            'label'       => esc_html__('Email content', 'bricks'),
            'type'        => 'textarea',
            'description' => __('<button onclick="BrfProForms.handleDefaultEmailContent(true, false, true)" class="button" style="text-decoration: none;margin-top: -2px">Refresh Email Content</button>', 'bricks'),
        ];

        $this->controls['refreshConfirmationEmailContent'] = [
            'tab'         => 'content',
            'group'       => 'confirmation',
            'label'       => esc_html__('Live Refresh Email Content in Builder', 'bricks'),
            'type'        => 'checkbox',
            'description' => esc_html__('Refresh Email Content in Builder automatically. If changing or adding fields, they will be automatically added in the email content.', 'bricks'),
        ];

        $this->controls['confirmationEmailHTML'] = [
            'tab'   => 'content',
            'group' => 'confirmation',
            'label' => esc_html__('HTML email', 'bricks'),
            'type'  => 'checkbox',
        ];

        // Group: Redirect

        $this->controls['redirectInfo'] = [
            'tab'     => 'content',
            'group'   => 'redirect',
            'content' => esc_html__('Redirect is only triggered after successful form submit.', 'bricks'),
            'type'    => 'info',
        ];

        $this->controls['redirectAdminUrl'] = [
            'tab'         => 'content',
            'group'       => 'redirect',
            'label'       => esc_html__('Redirect to admin area', 'bricks'),
            'type'        => 'checkbox',
            'placeholder' => admin_url(),
        ];

        $this->controls['redirect'] = [
            'tab'         => 'content',
            'group'       => 'redirect',
            'label'       => esc_html__('Custom redirect URL', 'bricks'),
            'type'        => 'text',
            'placeholder' => get_option('siteurl'),
        ];

        $this->controls['redirectTimeout'] = [
            'tab'   => 'content',
            'group' => 'redirect',
            'label' => esc_html__('Redirect after (ms)', 'bricks'),
            'type'  => 'number',
        ];

        // Group: Mailchimp (apiKeyMailchimp via global settings)

        $this->controls['mailchimpInfo'] = [
            'tab'      => 'content',
            'group'    => 'mailchimp',
            'content'  => sprintf(
                esc_html__('Mailchimp API key required! Add key in dashboard under: %s', 'bricks'),
                '<a href="' . Helpers::settings_url('#tab-api-keys') . '" target="_blank">' . esc_html__('Bricks > Settings > API Keys', 'bricks') . '</a>'
            ),
            'type'     => 'info',
            'required' => ['apiKeyMailchimp', '=', '', 'globalSettings'],
        ];

        $this->controls['mailchimpDoubleOptIn'] = [
            'tab'      => 'content',
            'group'    => 'mailchimp',
            'label'    => esc_html__('Double opt-in', 'bricks'),
            'type'     => 'checkbox',
            'required' => ['apiKeyMailchimp', '!=', '', 'globalSettings'],
        ];

        $mailchimp_list_options = [];

        foreach (Integrations\Form\Actions\Mailchimp::get_list_options() as $list_id => $list) {
            $mailchimp_list_options[$list_id] = $list['name'];
        }

        $this->controls['mailchimpList'] = [
            'tab'         => 'content',
            'group'       => 'mailchimp',
            'label'       => esc_html__('List', 'bricks'),
            'placeholder' => esc_html__('Select list', 'bricks'),
            'type'        => 'select',
            'options'     => $mailchimp_list_options,
            'required'    => ['actions', '=', 'mailchimp'],
            'required'    => ['apiKeyMailchimp', '!=', '', 'globalSettings'],
        ];

        $this->controls['mailchimpGroups'] = [
            'tab'         => 'content',
            'group'       => 'mailchimp',
            'label'       => esc_html__('Groups', 'bricks'),
            'placeholder' => esc_html__('Select group(s)', 'bricks'),
            'type'        => 'select',
            'options'     => [],
            // Populate in builder via 'mailchimpList' (PanelControl.vue)
            'multiple'    => true,
            'required'    => ['apiKeyMailchimp', '!=', '', 'globalSettings'],
        ];

        $this->controls['mailchimpEmail'] = [
            'tab'         => 'content',
            'group'       => 'mailchimp',
            'label'       => esc_html__('Email field *', 'bricks'),
            'placeholder' => esc_html__('Select email field', 'bricks'),
            'type'        => 'select',
            'options'     => [],
            // Auto-populate with form fields
            'map_fields'  => true,
            // NOTE: Undocumented
            'required'    => ['apiKeyMailchimp', '!=', '', 'globalSettings'],
        ];

        $this->controls['mailchimpFirstName'] = [
            'tab'         => 'content',
            'group'       => 'mailchimp',
            'label'       => esc_html__('First name', 'bricks'),
            'placeholder' => esc_html__('Select first name field', 'bricks'),
            'type'        => 'select',
            'options'     => [],
            // Auto-populate with form fields
            'map_fields'  => true,
            'required'    => ['apiKeyMailchimp', '!=', '', 'globalSettings'],
        ];

        $this->controls['mailchimpLastName'] = [
            'tab'         => 'content',
            'group'       => 'mailchimp',
            'label'       => esc_html__('Last name', 'bricks'),
            'placeholder' => esc_html__('Select last name field', 'bricks'),
            'type'        => 'select',
            'options'     => [],
            // Auto-populate with form fields
            'map_fields'  => true,
            'required'    => ['apiKeyMailchimp', '!=', '', 'globalSettings'],
        ];

        $this->controls['mailchimpPendingMessage'] = [
            'tab'      => 'content',
            'group'    => 'mailchimp',
            'label'    => esc_html__('Pending message', 'bricks'),
            'type'     => 'text',
            'required' => ['apiKeyMailchimp', '!=', '', 'globalSettings'],
            'default'  => esc_html__('Please check your email to confirm your subscription.', 'bricks'),
        ];

        $this->controls['mailchimpErrorMessage'] = [
            'tab'      => 'content',
            'group'    => 'mailchimp',
            'label'    => esc_html__('Error message', 'bricks'),
            'type'     => 'text',
            'required' => ['apiKeyMailchimp', '!=', '', 'globalSettings'],
            'default'  => esc_html__('Sorry, but we could not subscribe you.', 'bricks'),
        ];

        // Group: Sendgrid (apiKeySendgrid via global settings)

        $this->controls['sendgridInfo'] = [
            'tab'      => 'content',
            'group'    => 'sendgrid',
            'content'  => sprintf(
                esc_html__('Sendgrid API key required! Add key in dashboard under: %s', 'bricks'),
                '<a href="' . Helpers::settings_url('#tab-api-keys') . '" target="_blank">' . esc_html__('Bricks > Settings > API Keys', 'bricks') . '</a>'
            ),
            'type'     => 'info',
            'required' => ['apiKeySendgrid', '=', '', 'globalSettings'],
        ];

        $this->controls['sendgridList'] = [
            'tab'         => 'content',
            'group'       => 'sendgrid',
            'label'       => esc_html__('List', 'bricks'),
            'placeholder' => esc_html__('Select list', 'bricks'),
            'type'        => 'select',
            'options'     => Integrations\Form\Actions\Sendgrid::get_list_options(),
            'required'    => ['apiKeySendgrid', '!=', '', 'globalSettings'],
        ];

        $this->controls['sendgridEmail'] = [
            'tab'         => 'content',
            'group'       => 'sendgrid',
            'label'       => esc_html__('Email field *', 'bricks'),
            'placeholder' => esc_html__('Select email field', 'bricks'),
            'type'        => 'select',
            'options'     => [],
            // Auto-populate with form fields
            'map_fields'  => true,
            // NOTE: Undocumented
            'required'    => ['apiKeySendgrid', '!=', '', 'globalSettings'],
        ];

        $this->controls['sendgridFirstName'] = [
            'tab'         => 'content',
            'group'       => 'sendgrid',
            'label'       => esc_html__('First name field', 'bricks'),
            'placeholder' => esc_html__('Select first name field', 'bricks'),
            'type'        => 'select',
            'options'     => [],
            // Auto-populate with form fields
            'map_fields'  => true,
            'required'    => ['apiKeySendgrid', '!=', '', 'globalSettings'],
        ];

        $this->controls['sendgridLastName'] = [
            'tab'         => 'content',
            'group'       => 'sendgrid',
            'label'       => esc_html__('Last name field', 'bricks'),
            'placeholder' => esc_html__('Select last name field', 'bricks'),
            'type'        => 'select',
            'options'     => [],
            // Auto-populate with form fields
            'map_fields'  => true,
            'required'    => ['apiKeySendgrid', '!=', '', 'globalSettings'],
        ];

        // NOTE: Undocumented
        if (defined('BRICKS_SENDGRID_DOUBLE_OPT_IN') && BRICKS_SENDGRID_DOUBLE_OPT_IN) {
            $this->controls['sendgridPendingMessage'] = [
                'tab'      => 'content',
                'group'    => 'sendgrid',
                'label'    => esc_html__('Pending message', 'bricks'),
                'type'     => 'text',
                'required' => ['apiKeySendgrid', '!=', '', 'globalSettings'],
                'default'  => esc_html__('Please check your email to confirm your subscription.', 'bricks'),
            ];
        }

        $this->controls['sendgridErrorMessage'] = [
            'tab'      => 'content',
            'group'    => 'sendgrid',
            'label'    => esc_html__('Error message', 'bricks'),
            'type'     => 'text',
            'required' => ['apiKeySendgrid', '!=', '', 'globalSettings'],
            'default'  => esc_html__('Sorry, but we could not subscribe you.', 'bricks'),
        ];

        // Group: User Login

        $this->controls['loginName'] = [
            'tab'         => 'content',
            'group'       => 'login',
            'label'       => esc_html__('Login field *', 'bricks'),
            'placeholder' => esc_html__('Select login field', 'bricks'),
            'type'        => 'select',
            'options'     => [],
            // Auto-populate with form fields
            'map_fields'  => true, // NOTE: Undocumented
        ];

        $this->controls['loginPassword'] = [
            'tab'         => 'content',
            'group'       => 'login',
            'label'       => esc_html__('Password field', 'bricks'),
            'placeholder' => esc_html__('Select password field', 'bricks'),
            'type'        => 'select',
            'options'     => [],
            // Auto-populate with form fields
            'map_fields'  => true, // NOTE: Undocumented
        ];

        // Group: User Registration

        $this->controls['registrationEmail'] = [
            'tab'         => 'content',
            'group'       => 'registration',
            'label'       => esc_html__('Email field *', 'bricks'),
            'placeholder' => esc_html__('Select email field', 'bricks'),
            'type'        => 'select',
            'options'     => [],
            // Auto-populate with form fields
            'map_fields'  => true, // NOTE: Undocumented
        ];

        $this->controls['registrationPassword'] = [
            'tab'         => 'content',
            'group'       => 'registration',
            'label'       => esc_html__('Password field', 'bricks'),
            'placeholder' => esc_html__('Select password field', 'bricks'),
            'type'        => 'select',
            'options'     => [],
            // Auto-populate with form fields
            'map_fields'  => true,
            // NOTE: Undocumented
            'description' => esc_html__('Autogenerated if no password is required/submitted.', 'bricks'),
        ];

        $this->controls['registrationPasswordMinLength'] = [
            'tab'         => 'content',
            'group'       => 'registration',
            'label'       => esc_html__('Password min. length', 'bricks'),
            'type'        => 'number',
            'placeholder' => 6,
        ];

        $this->controls['registrationUserName'] = [
            'tab'         => 'content',
            'group'       => 'registration',
            'label'       => esc_html__('User name field', 'bricks'),
            'type'        => 'select',
            'options'     => [],
            // Auto-populate with form fields
            'map_fields'  => true,
            // NOTE: Undocumented
            'placeholder' => esc_html__('Select user name field', 'bricks'),
            'description' => esc_html__('Auto-generated if form only requires email address for registration.', 'bricks'),
        ];

        $this->controls['registrationFirstName'] = [
            'tab'         => 'content',
            'group'       => 'registration',
            'label'       => esc_html__('First name field', 'bricks'),
            'placeholder' => esc_html__('Select first name field', 'bricks'),
            'type'        => 'select',
            'options'     => [],
            // Auto-populate with form fields
            'map_fields'  => true,
        ];

        $this->controls['registrationLastName'] = [
            'tab'         => 'content',
            'group'       => 'registration',
            'label'       => esc_html__('Last name field', 'bricks'),
            'placeholder' => esc_html__('Select last name field', 'bricks'),
            'type'        => 'select',
            'options'     => [],
            // Auto-populate with form fields
            'map_fields'  => true,
        ];

        $this->controls['registrationAutoLogin'] = [
            'tab'         => 'content',
            'group'       => 'registration',
            'label'       => esc_html__('Auto log in user', 'bricks'),
            'type'        => 'checkbox',
            'description' => esc_html__('Log in user after successful registration. Tip: Set action "Redirect" to redirect user to the account/admin area.', 'bricks'),
        ];

        // Show Notifications in Builder
        $this->controls['showNotificationsInBuilder'] = [
            'tab'         => 'content',
            'group'       => 'notifications',
            'label'       => esc_html__('Show Notifications in Builder', 'bricks'),
            'type'        => 'checkbox',
            'rerender'    => true,
        ];

        // Group: Notifications
        $this->controls['notificationSuccessHeading'] = [
            'tab'     => 'content',
            'group'   => 'notifications',
            'label'   => esc_html__('Success Notifications', 'bricks'),
            'type'    => 'separator',
        ];

        $this->controls['notificationSuccessBackgroundColor'] = [
            'tab'     => 'content',
            'group'   => 'notifications',
            'label'   => esc_html__('Background Color', 'bricks'),
            'type'    => 'color',
            'css' => [
                [
                    'selector' => '.message.success',
                    'property' => 'background-color',
                ],
            ],
        ];

        $this->controls['notificationSuccessTypography'] = [
            'tab'     => 'content',
            'group'   => 'notifications',
            'label'   => esc_html__('Typography', 'bricks'),
            'type'    => 'typography',
            'css' => [
                [
                    'selector' => '.message.success .text',
                    'property' => 'typography',
                ],
            ],
        ];

        $this->controls['notificationSuccessPadding'] = [
            'tab'     => 'content',
            'group'   => 'notifications',
            'label'   => esc_html__('Padding', 'bricks'),
            'type'    => 'dimensions',
            'css' => [
                [
                    'selector' => '.message.success .text',
                    'property' => 'padding',
                ],
            ],
        ];

        $this->controls['notificationSuccessMargin'] = [
            'tab'     => 'content',
            'group'   => 'notifications',
            'label'   => esc_html__('Margin', 'bricks'),
            'type'    => 'dimensions',
            'css' => [
                [
                    'selector' => '.message.success',
                    'property' => 'margin',
                ],
            ],
        ];

        $this->controls['notificationSuccessBorderRadius'] = [
            'tab'     => 'content',
            'group'   => 'notifications',
            'label'   => esc_html__('Border', 'bricks'),
            'type'    => 'border',
            'css' => [
                [
                    'selector' => '.message.success',
                    'property' => 'border-radius',
                ],
            ],
        ];

        // Error Notififations
        $this->controls['notificationErrorHeading'] = [
            'tab'     => 'content',
            'group'   => 'notifications',
            'label'   => esc_html__('Error Notifications', 'bricks'),
            'type'    => 'separator',
        ];

        $this->controls['notificationErrorBackgroundColor'] = [
            'tab'     => 'content',
            'group'   => 'notifications',
            'label'   => esc_html__('Background Color', 'bricks'),
            'type'    => 'color',
            'css' => [
                [
                    'selector' => '.message.error',
                    'property' => 'background-color',
                ],
            ],
        ];

        $this->controls['notificationErrorTypography'] = [
            'tab'     => 'content',
            'group'   => 'notifications',
            'label'   => esc_html__('Typography', 'bricks'),
            'type'    => 'typography',
            'css' => [
                [
                    'selector' => '.message.error .text',
                    'property' => 'typography',
                ],
            ],
        ];

        $this->controls['notificationErrorPadding'] = [
            'tab'     => 'content',
            'group'   => 'notifications',
            'label'   => esc_html__('Padding', 'bricks'),
            'type'    => 'dimensions',
            'css' => [
                [
                    'selector' => '.message.error .text',
                    'property' => 'padding',
                ],
            ],
        ];

        $this->controls['notificationErrorMargin'] = [
            'tab'     => 'content',
            'group'   => 'notifications',
            'label'   => esc_html__('Margin', 'bricks'),
            'type'    => 'dimensions',
            'css' => [
                [
                    'selector' => '.message.error',
                    'property' => 'margin',
                ],
            ],
        ];

        $this->controls['notificationErrorBorderRadius'] = [
            'tab'     => 'content',
            'group'   => 'notifications',
            'label'   => esc_html__('Border', 'bricks'),
            'type'    => 'border',
            'css' => [
                [
                    'selector' => '.message.error',
                    'property' => 'border-radius',
                ],
            ],
        ];


        // Group: Spam Protection

        $this->controls['recaptchaInfo'] = [
            'tab'      => 'content',
            'group'    => 'spam',
            'content'  => sprintf(
                esc_html__('Google reCAPTCHA API key required! Add key in dashboard under: %s', 'bricks'),
                '<a href="' . Helpers::settings_url('#tab-api-keys') . '" target="_blank">' . esc_html__('Bricks > Settings > API Keys', 'bricks') . '</a>'
            ),
            'type'     => 'info',
            'required' => ['apiKeyGoogleRecaptcha', '=', '', 'globalSettings'],
        ];

        $this->controls['enableRecaptcha'] = [
            'tab'      => 'content',
            'group'    => 'spam',
            'label'    => esc_html__('Enable reCAPTCHA', 'bricks'),
            'type'     => 'checkbox',
            'required' => ['apiKeyGoogleRecaptcha', '!=', '', 'globalSettings'],
        ];

        // Turnstile
        if ($this->check_for_turnstile_keys()[0] === true) {
            $this->controls['enableTurnstile'] = [
                'tab'      => 'content',
                'group'    => 'spam',
                'label'    => esc_html__('Enable Turnstile', 'bricks'),
                'type'     => 'checkbox'
            ];
        } else {
            $this->controls['turnstileInfo'] = [
                'tab'      => 'content',
                'group'    => 'spam',
                'content'  => '<a href="https://www.cloudflare.com/de-de/products/turnstile/" target="_blank">Cloudflare Turnstile</a> ' . esc_html__('API key required! Add key in dashboard under: ', 'bricks') . 'Bricksforge -> Elements -> Pro Forms',
                'type'     => 'info',
            ];
        }

        /**
         * hCaptcha
         */

        if ($this->check_for_hcaptcha_keys()[0] === true) {
            $this->controls['enableHCaptcha'] = [
                'tab'      => 'content',
                'group'    => 'spam',
                'label'    => esc_html__('Enable hCaptcha', 'bricks'),
                'type'     => 'checkbox'
            ];
        } else {
            // hCaptcha Info like recaptchaInfo above
            $this->controls['hCaptchaInfo'] = [
                'tab'      => 'content',
                'group'    => 'spam',
                'content'  => '<a href="https://www.hcaptcha.com/" target="_blank">hCaptcha</a> ' . esc_html__('API key required! Add key in dashboard under: ', 'bricks') . 'Bricksforge -> Elements -> Pro Forms',
                'type'     => 'info',
            ];
        }

        // If enableHCaptcha, creat a separator HCaptcha
        if (isset($this->controls['enableHCaptcha'])) {
            $this->controls['hCaptchaSeparator'] = [
                'tab'      => 'content',
                'group'    => 'spam',
                'label'    => esc_html__('hCaptcha', 'bricks'),
                'type'     => 'separator',
                'required' => ['enableHCaptcha', '=', true],
            ];
        }

        // hCaptcha Theme
        $this->controls['hCaptchaTheme'] = [
            'tab'      => 'content',
            'group'    => 'spam',
            'label'    => esc_html__('Theme', 'bricks'),
            'type'     => 'select',
            'default'  => 'light',
            'options'  => [
                'light' => esc_html__('Light', 'bricks'),
                'dark'  => esc_html__('Dark', 'bricks'),
            ],
            'required' => ['enableHCaptcha', '=', true],
        ];

        // Also create a text field "Error Message" for hCaptcha
        $this->controls['hCaptchaInfoMessage'] = [
            'tab'      => 'content',
            'group'    => 'spam',
            'label'    => esc_html__('Custom Info Message', 'bricks'),
            'type'     => 'text',
            'required' => ['enableHCaptcha', '=', true],
            'placeholder' => esc_html__('Please verify that you are not a robot.', 'bricks'),
        ];

        if (isset($this->controls['enableTurnstile'])) {
            $this->controls['turnstileSeparator'] = [
                'tab'      => 'content',
                'group'    => 'spam',
                'label'    => esc_html__('Turnstile', 'bricks'),
                'type'     => 'separator',
                'required' => ['enableTurnstile', '=', true],
            ];

            // Appearance
            $this->controls['turnstileAppearance'] = [
                'tab'      => 'content',
                'group'    => 'spam',
                'label'    => esc_html__('Appearance', 'bricks'),
                'type'     => 'select',
                'options'  => [
                    'always' => esc_html__('Always', 'bricks'),
                    'execute'  => esc_html__('Execute', 'bricks'),
                    'interaction-only' => esc_html__('Interaction only', 'bricks'),
                ],
                'default'  => 'always',
                'required' => ['enableTurnstile', '=', true],
            ];

            // Theme
            $this->controls['turnstileTheme'] = [
                'tab'      => 'content',
                'group'    => 'spam',
                'label'    => esc_html__('Theme', 'bricks'),
                'type'     => 'select',
                'options'  => [
                    'light' => esc_html__('Light', 'bricks'),
                    'dark'  => esc_html__('Dark', 'bricks'),
                ],
                'default'  => 'light',
                'required' => ['enableTurnstile', '=', true],
            ];

            // Size
            $this->controls['turnstileSize'] = [
                'tab'      => 'content',
                'group'    => 'spam',
                'label'    => esc_html__('Size', 'bricks'),
                'type'     => 'select',
                'options'  => [
                    'normal' => esc_html__('Normal', 'bricks'),
                    'compact'  => esc_html__('Compact', 'bricks'),
                ],
                'default'  => 'normal',
                'required' => ['enableTurnstile', '=', true],
            ];

            // Language
            $this->controls['turnstileLanguage'] = [
                'tab'      => 'content',
                'group'    => 'spam',
                'label'    => esc_html__('Language', 'bricks'),
                'type'     => 'text',
                'required' => ['enableTurnstile', '=', true],
                'description' => esc_html__('Enter the language code (e.g. "en" or "de"). Auto if empty.', 'bricks'),
            ];

            // Error Message
            $this->controls['turnstileErrorMessage'] = [
                'tab'      => 'content',
                'group'    => 'spam',
                'label'    => esc_html__('Custom Error Message', 'bricks'),
                'type'     => 'text',
                'required' => ['enableTurnstile', '=', true],
                'default' => esc_html__('Your submission is being verified. Please wait a moment before submitting again.', 'bricks'),
            ];
        }


        // Upload Button (remove "Text" control group)

        // @since: 1.4 = deprecated (moved within repeater field. see line 225)
        $this->controls['uploadButtonTypography'] = [
            'tab'        => 'content',
            'label'      => esc_html__('File upload', 'bricks') . ' - ' . esc_html__('Typography', 'bricks'),
            'type'       => 'typography',
            'css'        => [
                [
                    'property' => 'font',
                    'selector' => '.choose-files',
                ],
            ],
            'deprecated' => true,
        ];

        $this->controls['uploadButtonBackgroundColor'] = [
            'tab'        => 'content',
            'label'      => esc_html__('File upload', 'bricks') . ' - ' . esc_html__('Background', 'bricks'),
            'type'       => 'color',
            'css'        => [
                [
                    'property' => 'background-color',
                    'selector' => '.choose-files',
                ],
            ],
            'deprecated' => true,
        ];

        $this->controls['uploadButtonBorder'] = [
            'tab'        => 'content',
            'label'      => esc_html__('File upload', 'bricks') . ' - ' . esc_html__('Border', 'bricks'),
            'type'       => 'border',
            'css'        => [
                [
                    'property' => 'border',
                    'selector' => '.choose-files',
                ],
            ],
            'deprecated' => true,
        ];

        // Group: Multi Step
        $this->controls['multiStepPreviousText'] = [
            'tab'    => 'content',
            'group'  => 'multistep',
            'label'  => esc_html__('Previous Text', 'bricks'),
            'type'   => 'text',
            'inline' => true,
        ];

        $this->controls['multiStepNextText'] = [
            'tab'    => 'content',
            'group'  => 'multistep',
            'label'  => esc_html__('Next Text', 'bricks'),
            'type'   => 'text',
            'inline' => true,
        ];

        $this->controls['multiStepButtonsFlexDirection'] = [
            'tab'     => 'content',
            'group'   => 'multistep',
            'label'   => esc_html__('Flex Direction', 'bricks'),
            'type'    => 'direction',
            'css'     => [
                [
                    'property' => 'flex-direction',
                    'selector' => '.step-progress',
                ],
            ],
        ];

        $this->controls['multiStepButtonBackground'] = [
            'tab'    => 'content',
            'group'  => 'multistep',
            'label'  => esc_html__('Step Button Background', 'bricks'),
            'type'   => 'color',
            'inline' => true,
            'css'    => [
                [
                    'property' => 'background-color',
                    'selector' => '.step-progress button',
                ],
            ],
        ];

        $this->controls['multiStepButtonTypography'] = [
            'tab'    => 'content',
            'group'  => 'multistep',
            'label'  => esc_html__('Step Button Typography', 'bricks'),
            'type'   => 'typography',
            'inline' => true,
            'css'    => [
                [
                    'property' => 'typography',
                    'selector' => '.step-progress button',
                ],
            ],
        ];

        $this->controls['multiStepButtonBorder'] = [
            'tab'    => 'content',
            'group'  => 'multistep',
            'label'  => esc_html__('Step Button Border', 'bricks'),
            'type'   => 'border',
            'inline' => true,
            'css'    => [
                [
                    'property' => 'border',
                    'selector' => '.step-progress button',
                ],
            ],
        ];

        $this->controls['multiStepButtonPadding'] = [
            'tab'   => 'content',
            'group' => 'multistep',
            'label' => esc_html__('Step Button Padding', 'bricks'),
            'type'  => 'spacing',
            'css'   => [
                [
                    'property' => 'padding',
                    'selector' => '.step-progress button',
                ],
            ],
        ];

        $this->controls['multiStepButtonMargin'] = [
            'tab'   => 'content',
            'group' => 'multistep',
            'label' => esc_html__('Step Button Margin', 'bricks'),
            'type'  => 'spacing',
            'css'   => [
                [
                    'property' => 'margin',
                    'selector' => '.step-progress button',
                ],
            ],
        ];

        // Step Button Gap
        $this->controls['multiStepButtonGap'] = [
            'tab'   => 'content',
            'group' => 'multistep',
            'label' => esc_html__('Step Buttons Gap', 'bricks'),
            'type'  => 'number',
            'unit' => 'px',
            'css'   => [
                [
                    'property' => 'gap',
                    'selector' => '.step-progress',
                ],
            ],
        ];

        $this->controls['multiStepSummary'] = [
            'tab'     => 'content',
            'group'   => 'multistep',
            'label'   => esc_html__('Show Summary', 'bricks'),
            'type'    => 'checkbox',
            'default' => false,
        ];

        $this->controls['multiStepShowSteps'] = [
            'tab'     => 'content',
            'group'   => 'multistep',
            'label'   => esc_html__('Show Steps', 'bricks'),
            'type'    => 'checkbox',
            'default' => false,
        ];

        $this->controls['multiStepSummaryButtonText'] = [
            'tab'     => 'content',
            'group'   => 'multistepSummary',
            'label'   => esc_html__('Button Text', 'bricks'),
            'type'    => 'text',
            'default' => 'Show Summary'
        ];

        $this->controls['multiStepSummaryButtonBackground'] = [
            'tab'    => 'content',
            'group'  => 'multistepSummary',
            'label'  => esc_html__('Button Background', 'bricks'),
            'type'   => 'color',
            'inline' => true,
            'css'    => [
                [
                    'property' => 'background-color',
                    'selector' => 'button.summary',
                ],
            ],
        ];

        $this->controls['multiStepSummaryButtonTypography'] = [
            'tab'    => 'content',
            'group'  => 'multistepSummary',
            'label'  => esc_html__('Button Typography', 'bricks'),
            'type'   => 'typography',
            'inline' => true,
            'css'    => [
                [
                    'property' => 'typography',
                    'selector' => 'button.summary',
                ],
            ],
        ];

        $this->controls['multiStepSummaryButtonBorder'] = [
            'tab'    => 'content',
            'group'  => 'multistepSummary',
            'label'  => esc_html__('Button Border', 'bricks'),
            'type'   => 'border',
            'inline' => true,
            'css'    => [
                [
                    'property' => 'border',
                    'selector' => 'button.summary',
                ],
            ],
        ];

        $this->controls['multiStepSummaryButtonPadding'] = [
            'tab'   => 'content',
            'group' => 'multistepSummary',
            'label' => esc_html__('Button Padding', 'bricks'),
            'type'  => 'spacing',
            'css'   => [
                [
                    'property' => 'padding',
                    'selector' => 'button.summary',
                ],
            ],
        ];

        $this->controls['multiStepSummaryButtonMargin'] = [
            'tab'   => 'content',
            'group' => 'multistepSummary',
            'label' => esc_html__('Button Margin', 'bricks'),
            'type'  => 'spacing',
            'css'   => [
                [
                    'property' => 'margin',
                    'selector' => 'button.summary',
                ],
            ],
        ];

        $this->controls['multiStepSummaryContainerBackground'] = [
            'tab'   => 'content',
            'group' => 'multistepSummary',
            'label' => esc_html__('Container Background', 'bricks'),
            'type'  => 'color',
            'css'   => [
                [
                    'property' => 'background-color',
                    'selector' => '#brf-summary',
                ],
            ],
        ];

        $this->controls['multiStepSummaryContainerMargin'] = [
            'tab'   => 'content',
            'group' => 'multistepSummary',
            'label' => esc_html__('Container Margin', 'bricks'),
            'type'  => 'spacing',
            'css'   => [
                [
                    'property' => 'margin',
                    'selector' => '#brf-summary',
                ],
            ],
        ];

        $this->controls['multiStepSummaryContainerPadding'] = [
            'tab'   => 'content',
            'group' => 'multistepSummary',
            'label' => esc_html__('Container Padding', 'bricks'),
            'type'  => 'spacing',
            'css'   => [
                [
                    'property' => 'padding',
                    'selector' => '#brf-summary',
                ],
            ],
        ];

        $this->controls['multiStepSummaryMainHeadline'] = [
            'tab'     => 'content',
            'group'   => 'multistepSummary',
            'label'   => esc_html__('Main Headline', 'bricks'),
            'type'    => 'text',
            'default' => 'Summary'
        ];

        $this->controls['multiStepSummaryMainHeadlineTypography'] = [
            'tab'   => 'content',
            'group' => 'multistepSummary',
            'label' => esc_html__('Main Headline Typography', 'bricks'),
            'type'  => 'typography',
            'css'   => [
                [
                    'property' => 'typography',
                    'selector' => '.brf-summary-headline',
                ],
            ],
        ];

        $this->controls['multiStepSummaryMainHeadlineMargin'] = [
            'tab'   => 'content',
            'group' => 'multistepSummary',
            'label' => esc_html__('Main Headline Margin', 'bricks'),
            'type'  => 'spacing',
            'css'   => [
                [
                    'property' => 'margin',
                    'selector' => '.brf-summary-headline',
                ],
            ],
        ];

        $this->controls['multiStepSummaryItemBackground'] = [
            'tab'   => 'content',
            'group' => 'multistepSummary',
            'label' => esc_html__('Item Background', 'bricks'),
            'type'  => 'color',
            'css'   => [
                [
                    'property' => 'background-color',
                    'selector' => '#brf-summary .brf-summary-item',
                ],
            ],
        ];

        $this->controls['multiStepSummaryItemMargin'] = [
            'tab'   => 'content',
            'group' => 'multistepSummary',
            'label' => esc_html__('Item Margin', 'bricks'),
            'type'  => 'spacing',
            'css'   => [
                [
                    'property' => 'margin',
                    'selector' => '#brf-summary .brf-summary-item',
                ],
            ],
        ];

        $this->controls['multiStepSummaryItemPadding'] = [
            'tab'   => 'content',
            'group' => 'multistepSummary',
            'label' => esc_html__('Item Padding', 'bricks'),
            'type'  => 'spacing',
            'css'   => [
                [
                    'property' => 'padding',
                    'selector' => '#brf-summary .brf-summary-item',
                ],
            ],
        ];

        $this->controls['multiStepSummaryItemBorder'] = [
            'tab'   => 'content',
            'group' => 'multistepSummary',
            'label' => esc_html__('Item Border', 'bricks'),
            'type'  => 'border',
            'css'   => [
                [
                    'property' => 'border',
                    'selector' => '#brf-summary .brf-summary-item',
                ],
            ],
        ];

        $this->controls['multiStepSummaryHeadlineTypography'] = [
            'tab'   => 'content',
            'group' => 'multistepSummary',
            'label' => esc_html__('Item Headline Typography', 'bricks'),
            'type'  => 'typography',
            'css'   => [
                [
                    'property' => 'typography',
                    'selector' => '#brf-summary .brf-summary-item h4',
                ],
            ],
        ];

        $this->controls['multiStepSummaryValueTypography'] = [
            'tab'   => 'content',
            'group' => 'multistepSummary',
            'label' => esc_html__('Item Value Typography', 'bricks'),
            'type'  => 'typography',
            'css'   => [
                [
                    'property' => 'typography',
                    'selector' => '#brf-summary .brf-summary-item p',
                ],
            ],
        ];

        $this->controls['multiStepSummaryShowEmpty'] = [
            'tab'     => 'content',
            'group'   => 'multistepSummary',
            'label'   => esc_html__('Show Empty Fields', 'bricks'),
            'type'    => 'checkbox',
            'default' => false
        ];

        $this->controls['multiStepSummaryEmptyText'] = [
            'tab'      => 'content',
            'group'    => 'multistepSummary',
            'label'    => esc_html__('Text for empty fields', 'bricks'),
            'type'     => 'text',
            'default'  => '/',
            'required' => ['multiStepSummaryShowEmpty', '=', true],
        ];

        // Step Settings

        $this->controls['multiStepFirstStep'] = [
            'tab'     => 'content',
            'group'   => 'multistepStep',
            'label'   => esc_html__('First Step Text', 'bricks'),
            'type'    => 'text',
            'default' => 'Start'
        ];

        $this->controls['multiStepStepTypography'] = [
            'tab'   => 'content',
            'group' => 'multistepStep',
            'label' => esc_html__('Step Typography', 'bricks'),
            'type'  => 'typography',
            'css'   => [
                [
                    'property' => 'typography',
                    'selector' => '.brf-step',
                ],
            ],
        ];

        $this->controls['multiStepCurrentStepTypography'] = [
            'tab'   => 'content',
            'group' => 'multistepStep',
            'label' => esc_html__('Current Step Typography', 'bricks'),
            'type'  => 'typography',
            'css'   => [
                [
                    'property' => 'typography',
                    'selector' => '.brf-step.current',
                ],
            ],
        ];

        $this->controls['multiStepStepTopOffset'] = [
            'tab'     => 'content',
            'group'   => 'multistepStep',
            'label'   => esc_html__('Top Offset', 'bricks'),
            'type'    => 'number',
            'units'   => ['px'],
            'css'     => [
                [
                    'property' => 'top',
                    'selector' => '.brf-steps',
                ],
            ],
            'inset'   => true,
            'default' => '-60px'
        ];

        $this->controls['multiStepStepJustifyContent'] = [
            'tab'   => 'content',
            'group' => 'multistepStep',
            'label' => esc_html__('Justify Content', 'bricks'),
            'type'  => 'justify-content',
            'css'   => [
                [
                    'property' => 'justify-content',
                    'selector' => '.brf-steps',
                ],
            ],
        ];

        $this->controls['multiStepStepGap'] = [
            'tab'   => 'content',
            'group' => 'multistepStep',
            'label' => esc_html__('Gap', 'bricks'),
            'type'  => 'number',
            'units' => ['px'],
            'css'   => [
                [
                    'property' => 'gap',
                    'selector' => '.brf-steps',
                ],
            ],
        ];

        $this->controls['multiStepStepAllowClicks'] = [
            'tab'     => 'content',
            'group'   => 'multistepStep',
            'label'   => esc_html__('Allow Clicks on Steps', 'bricks'),
            'type'    => 'checkbox',
            'default' => false
        ];

        // Create New Post
        $this->controls['pro_forms_post_action_post_create_cat_info'] = [
            'type' => 'info',
            'tab'  => 'content',
            'group' => 'pro_forms_post_action_post_create',
            'content' => esc_html__('Each field also accepts Form Field IDs', 'bricks'),
        ];

        $this->controls['pro_forms_post_action_post_create_post_status'] = [
            'tab'     => 'content',
            'group'   => 'pro_forms_post_action_post_create',
            'label'   => esc_html__('Post Status', 'bricks'),
            'type'    => 'select',
            'options' => [
                'publish' => esc_html__('Publish', 'bricks'),
                'draft'   => esc_html__('Draft', 'bricks'),
                'pending' => esc_html__('Pending', 'bricks'),
                'private' => esc_html__('Private', 'bricks'),
                'future'  => esc_html__('Future', 'bricks'),
            ],
            'default' => 'draft'
        ];

        // Author
        $this->controls['pro_forms_post_action_post_create_author'] = [
            'type' => 'text',
            'tab'  => 'content',
            'label' => esc_html__('Author (User ID. Leave empty for default author)', 'bricks'),
            'group' => 'pro_forms_post_action_post_create',
        ];
        $this->controls['pro_forms_post_action_post_create_pt'] = [
            'tab'     => 'content',
            'group'   => 'pro_forms_post_action_post_create',
            'label'   => esc_html__('Post Type', 'bricks'),
            'type'    => 'select',
            'options' => $this->get_post_types(),
            'default' => 'post'
        ];

        $this->controls['pro_forms_post_action_post_create_categories'] = [
            'tab'           => 'content',
            'group'         => 'pro_forms_post_action_post_create',
            'label'         => esc_html__('Post Categories', 'bricks'),
            'type'          => 'repeater',
            'titleProperty' => 'category',
            'fields'        => [
                'category' => [
                    'label' => esc_html__('Category Slug', 'bricks'),
                    'type'  => 'text',
                ],
            ]
        ];
        $this->controls['pro_forms_post_action_post_create_taxonomies'] = [
            'tab'           => 'content',
            'group'         => 'pro_forms_post_action_post_create',
            'label'         => esc_html__('Post Taxonomies', 'bricks'),
            'type'          => 'repeater',
            'titleProperty' => 'taxonomy',
            'fields'        => [
                'taxonomy' => [
                    'label' => esc_html__('Taxonomy Slug', 'bricks'),
                    'type'  => 'text',
                ],
                'term' => [
                    'label' => esc_html__('Term', 'bricks'),
                    'type'  => 'text',
                ],
            ]
        ];
        $this->controls['pro_forms_post_action_post_create_title'] = [
            'tab'         => 'content',
            'group'       => 'pro_forms_post_action_post_create',
            'label'       => esc_html__('Post Title', 'bricks'),
            'type'        => 'text',
            'placeholder' => 'Enter Form Field ID'
        ];
        $this->controls['pro_forms_post_action_post_create_content'] = [
            'tab'         => 'content',
            'group'       => 'pro_forms_post_action_post_create',
            'label'       => esc_html__('Post Content', 'bricks'),
            'type'        => 'text',
            'placeholder' => 'Enter Form Field ID'
        ];
        $this->controls['pro_forms_post_action_post_create_thumbnail'] = [
            'tab'         => 'content',
            'group'       => 'pro_forms_post_action_post_create',
            'label'       => esc_html__('Post Thumbnail', 'bricks'),
            'type'        => 'text',
            'placeholder' => 'Enter Form Field ID'
        ];

        $this->controls['pro_forms_post_action_post_create_custom_fields'] = [
            'tab'           => 'content',
            'group'         => 'pro_forms_post_action_post_create',
            'label'         => esc_html__('Custom Fields', 'bricks'),
            'type'          => 'repeater',
            'titleProperty' => 'name',
            'placeholder'   => 'Custom Field',
            'fields'        => [
                'name'  => [
                    'label' => esc_html__('Field Name', 'bricks'),
                    'type'  => 'text',
                ],
                'value' => [
                    'label'       => esc_html__('Field Value', 'bricks'),
                    'type'        => 'text',
                    'placeholder' => 'Enter Form Field ID'
                ],
            ]
        ];

        // Update Post
        $this->controls['pro_forms_post_action_post_update_cat_info'] = [
            'type' => 'info',
            'tab'  => 'content',
            'group' => 'pro_forms_post_action_post_update',
            'content' => esc_html__('Each field also accepts Form Field IDs', 'bricks'),
        ];

        $this->controls['pro_forms_post_action_post_update_post_id'] = [
            'tab'         => 'content',
            'group'       => 'pro_forms_post_action_post_update',
            'label'       => esc_html__('Post ID', 'bricks'),
            'type'        => 'text',
            'placeholder' => 'Enter Form Field ID'
        ];

        $this->controls['pro_forms_post_action_post_update_title'] = [
            'tab'         => 'content',
            'group'       => 'pro_forms_post_action_post_update',
            'label'       => esc_html__('Post Title', 'bricks'),
            'type'        => 'text',
            'placeholder' => 'Enter Form Field ID'
        ];

        $this->controls['pro_forms_post_action_post_update_content'] = [
            'tab'         => 'content',
            'group'       => 'pro_forms_post_action_post_update',
            'label'       => esc_html__('Post Content', 'bricks'),
            'type'        => 'text',
            'placeholder' => 'Enter Form Field ID'
        ];

        // Post Status as text field
        $this->controls['pro_forms_post_action_post_update_status'] = [
            'tab'         => 'content',
            'group'       => 'pro_forms_post_action_post_update',
            'label'       => esc_html__('Post Status', 'bricks'),
            'type'        => 'text',
            'placeholder' => 'Enter Form Field ID',
            'description' => 'Available: draft, pending, publish, future, private, trash'
        ];

        // Post Excerpt
        $this->controls['pro_forms_post_action_post_update_excerpt'] = [
            'tab'         => 'content',
            'group'       => 'pro_forms_post_action_post_update',
            'label'       => esc_html__('Post Excerpt', 'bricks'),
            'type'        => 'text',
            'placeholder' => 'Enter Form Field ID'
        ];


        // Post Date

        $this->controls['pro_forms_post_action_post_update_date'] = [
            'tab'         => 'content',
            'group'       => 'pro_forms_post_action_post_update',
            'label'       => esc_html__('Post Date', 'bricks'),
            'type'        => 'text',
            'placeholder' => 'Enter Form Field ID'
        ];

        // Post Thumbnail

        $this->controls['pro_forms_post_action_post_update_thumbnail'] = [
            'tab'         => 'content',
            'group'       => 'pro_forms_post_action_post_update',
            'label'       => esc_html__('Post Thumbnail', 'bricks'),
            'type'        => 'text',
            'placeholder' => 'Enter Form Field ID'
        ];

        // Update Option
        $this->controls['pro_forms_post_action_option_add_option_data'] = [
            'tab'           => 'content',
            'group'         => 'pro_forms_post_action_add_option',
            'label'         => esc_html__('Option Data', 'bricks'),
            'type'          => 'repeater',
            'titleProperty' => 'name',
            'fields'        => [
                'name'  => [
                    'label' => esc_html__('Option Name', 'bricks'),
                    'type'  => 'text',
                ],
                'value' => [
                    'label'       => esc_html__('Option Value', 'bricks'),
                    'type'        => 'text',
                    'placeholder' => 'Enter Form Field ID'
                ],
            ]
        ];

        $this->controls['pro_forms_post_action_option_update_option_data'] = [
            'tab'           => 'content',
            'group'         => 'pro_forms_post_action_update_option',
            'label'         => esc_html__('Option Data', 'bricks'),
            'type'          => 'repeater',
            'titleProperty' => 'name',
            'fields'        => [
                'name'         => [
                    'label' => esc_html__('Option Name', 'bricks'),
                    'type'  => 'text',
                ],
                'value'        => [
                    'label'       => esc_html__('Option Value', 'bricks'),
                    'type'        => 'text',
                    'placeholder' => 'Enter Form Field ID',
                    'required'    => [['type', '!=', 'increment'], ['type', '!=', 'decrement'], ['type', '!=', 'increment_by_number'], ['type', '!=', 'decrement_by_number']]
                ],
                'type'         => [
                    'label'   => esc_html__('Update Type', 'bricks'),
                    'type'    => 'select',
                    'options' => [
                        'replace'             => esc_html__('Replace Value', 'bricks'),
                        'increment'           => esc_html__('Increment Number', 'bricks'),
                        'decrement'           => esc_html__('Decrement Number', 'bricks'),
                        'increment_by_number' => esc_html__('Increment by Number', 'bricks'),
                        'decrement_by_number' => esc_html__('Decrement by Number', 'bricks'),
                        'add_to_array'        => esc_html__('Add to Array', 'bricks'),
                        'remove_from_array'   => esc_html__('Remove from Array', 'bricks'),
                    ],
                    'default' => 'replace'
                ],
                'number_field' => [
                    'required'    => [['type', '=', ['increment_by_number', 'decrement_by_number']]],
                    'label'       => esc_html__('Select Number Field', 'bricks'),
                    'type'        => 'text',
                    'placeholder' => 'Enter Form Field ID',
                ],
                'selector'     => [
                    'label'       => esc_html__('Live Update Selector', 'bricks'),
                    'type'        => 'text',
                    'placeholder' => '.selector',
                    'description' => esc_html__('Enter a selector from the element you want to live change the value with the new value from the database', 'bricks'),
                    'required'    => [['type', '!=', 'add_to_array'], ['type', '!=', 'remove_from_array']]
                ],
            ]
        ];

        $this->controls['pro_forms_post_action_option_delete_option_data'] = [
            'tab'           => 'content',
            'group'         => 'pro_forms_post_action_delete_option',
            'label'         => esc_html__('Option Data', 'bricks'),
            'type'          => 'repeater',
            'titleProperty' => 'name',
            'fields'        => [
                'name' => [
                    'label' => esc_html__('Option Name', 'bricks'),
                    'type'  => 'text',
                ],
            ]
        ];

        // Update Post Meta
        $this->controls['pro_forms_post_action_update_post_meta_data'] = [
            'tab'           => 'content',
            'group'         => 'pro_forms_post_action_update_post_meta',
            'label'         => esc_html__('Post Meta Data', 'bricks'),
            'type'          => 'repeater',
            'titleProperty' => 'name',
            'fields'        => [
                'post_id'      => [
                    'label'       => esc_html__('Post ID', 'bricks'),
                    'type'        => 'text',
                    'placeholder' => 'Leave Empty For Current Post ID',
                ],
                'name'         => [
                    'label' => esc_html__('Post Meta Name', 'bricks'),
                    'type'  => 'text',
                ],
                'value'        => [
                    'label'       => esc_html__('Post Meta Value', 'bricks'),
                    'type'        => 'text',
                    'placeholder' => 'Enter Form Field ID',
                    'required'    => [['type', '!=', 'increment'], ['type', '!=', 'decrement'], ['type', '!=', 'increment_by_number'], ['type', '!=', 'decrement_by_number']]
                ],
                'type'         => [
                    'label'   => esc_html__('Update Type', 'bricks'),
                    'type'    => 'select',
                    'options' => [
                        'replace'             => esc_html__('Replace Value', 'bricks'),
                        'increment'           => esc_html__('Increment Number', 'bricks'),
                        'decrement'           => esc_html__('Decrement Number', 'bricks'),
                        'increment_by_number' => esc_html__('Increment by Number', 'bricks'),
                        'decrement_by_number' => esc_html__('Decrement by Number', 'bricks'),
                        'add_to_array'        => esc_html__('Add to Array', 'bricks'),
                        'remove_from_array'   => esc_html__('Remove from Array', 'bricks'),
                    ],
                    'default' => 'replace'
                ],
                'number_field' => [
                    'required'    => [['type', '=', ['increment_by_number', 'decrement_by_number']]],
                    'label'       => esc_html__('Select Number Field', 'bricks'),
                    'type'        => 'text',
                    'placeholder' => 'Enter Form Field ID',
                ],
                'ignore_empty' => [
                    'label'        => esc_html__('Ignore if empty', 'bricks'),
                    'type'         => 'checkbox',
                    'default'      => false,
                    'description'  => esc_html__('If checked, the post meta will not be updated when the form field value is empty', 'bricks'),
                ],
                'selector'     => [
                    'required'    => [['type', '!=', 'add_to_array'], ['type', '!=', 'remove_from_array']],
                    'label'       => esc_html__('Live Update Selector', 'bricks'),
                    'type'        => 'text',
                    'placeholder' => '.selector',
                    'description' => esc_html__('Enter a selector from the element you want to live change the value with the new value from the database', 'bricks'),
                ],
            ]
        ];

        // Set Storage Item
        $this->controls['pro_forms_post_action_set_storage_item_data'] = [
            'tab'           => 'content',
            'group'         => 'pro_forms_post_action_set_storage_item',
            'label'         => esc_html__('Storage Item Data', 'bricks'),
            'type'          => 'repeater',
            'titleProperty' => 'name',
            'fields'        => [
                'name'         => [
                    'label' => esc_html__('Storage Item Name', 'bricks'),
                    'type'  => 'text',
                ],
                'value'        => [
                    'label'       => esc_html__('Storage Item Value', 'bricks'),
                    'type'        => 'text',
                    'placeholder' => 'Enter Form Field ID',
                    'required'    => [['type', '!=', 'increment'], ['type', '!=', 'decrement'], ['type', '!=', 'increment_by_number'], ['type', '!=', 'decrement_by_number']]
                ],
                'type'         => [
                    'label'   => esc_html__('Update Type', 'bricks'),
                    'type'    => 'select',
                    'options' => [
                        'replace'             => esc_html__('Replace Value', 'bricks'),
                        'increment'           => esc_html__('Increment Number', 'bricks'),
                        'decrement'           => esc_html__('Decrement Number', 'bricks'),
                        'increment_by_number' => esc_html__('Increment by Number', 'bricks'),
                        'decrement_by_number' => esc_html__('Decrement by Number', 'bricks'),
                        'add_to_array'        => esc_html__('Add to Array', 'bricks'),
                        'remove_from_array'   => esc_html__('Remove from Array', 'bricks'),
                    ],
                    'default' => 'replace'
                ],
                'number_field' => [
                    'required'    => [['type', '=', ['increment_by_number', 'decrement_by_number']]],
                    'label'       => esc_html__('Select Number Field', 'bricks'),
                    'type'        => 'text',
                    'placeholder' => 'Enter Form Field ID',
                ],
                'selector'     => [
                    'label'       => esc_html__('Live Update Selector', 'bricks'),
                    'type'        => 'text',
                    'placeholder' => '.selector',
                    'description' => esc_html__('Enter a selector from the element you want to live change the value with the new value from the database', 'bricks'),
                    'required'    => [['type', '!=', 'add_to_array'], ['type', '!=', 'remove_from_array']]
                ],
            ]
        ];

        // Group: Submissions
        $this->controls['submission_form_title'] = [
            'tab'         => 'content',
            'group'       => 'submissions',
            'label'       => esc_html__('Form Title', 'bricks'),
            'type'        => 'text',
            'description' => esc_html__('Normally your form is marked with an ID. If you want to define a fixed name for it, enter it here. The name will be displayed in the submissions table.', 'bricks'),
        ];
        $this->controls['submission_max'] = [
            'tab'         => 'content',
            'group'       => 'submissions',
            'label'       => esc_html__('Maximum Submissions', 'bricks'),
            'type'        => 'number',
            'inline'      => false,
            'description' => esc_html__('Enter the maximum number of submissions you want to store. If you leave this field empty, all submissions will be stored.', 'bricks'),
        ];
        $this->controls['submission_prevent_duplicates'] = [
            'tab'   => 'content',
            'group' => 'submissions',
            'label' => esc_html__('Prevent Duplicates', 'bricks'),
            'type'  => 'checkbox',
        ];
        $this->controls['submission_prevent_duplicates_data'] = [
            'required'      => [['submission_prevent_duplicates', '=', true]],
            'tab'           => 'content',
            'group'         => 'submissions',
            'label'         => esc_html__('For Field', 'bricks'),
            'type'          => 'repeater',
            'titleProperty' => 'field',
            'fields'        => [
                'field'  => [
                    'label' => esc_html__('Form Field ID', 'bricks'),
                    'type'  => 'text',
                ],
                'notice' => [
                    'label'       => esc_html__('Notice', 'bricks'),
                    'type'        => 'text',
                    'placeholder' => esc_html__('This email address is already in use', 'bricks'),
                ],

            ]
        ];

        // WooCommerce: Add To Cart

        // Product (Select)
        $this->controls['pro_forms_post_action_add_to_cart_product'] = [
            'tab'         => 'content',
            'group'       => 'wcAddToCart',
            'label'       => esc_html__('Product', 'bricks'),
            'type'        => 'select',
            'options'     => $this->get_wc_products(),
            'searchable' => true,
            'description' => esc_html__('The product you want to add to the cart.', 'bricks'),
        ];

        // Product ID
        $this->controls['pro_forms_post_action_add_to_cart_product_id'] = [
            'tab'         => 'content',
            'group'       => 'wcAddToCart',
            'type'       => 'text',
            'label'       => esc_html__('Product ID', 'bricks'),
            'description' => esc_html__('The ID of the product you want to add to the cart.', 'bricks'),
            'required'    => [['pro_forms_post_action_add_to_cart_product', '=', 'custom']]
        ];

        // Quantity
        $this->controls['pro_forms_post_action_add_to_cart_quantity'] = [
            'tab'         => 'content',
            'group'       => 'wcAddToCart',
            'type'       => 'text',
            'label'       => esc_html__('Quantity', 'bricks'),
            'description' => esc_html__('The quantity of the product you want to add to the cart.', 'bricks'),
        ];

        // Price
        $this->controls['pro_forms_post_action_add_to_cart_price'] = [
            'tab'         => 'content',
            'group'       => 'wcAddToCart',
            'type'       => 'text',
            'label'       => esc_html__('Price', 'bricks'),
            'description' => esc_html__('The price of the product you want to add to the cart.', 'bricks'),
        ];

        // Is Total Price
        $this->controls['pro_forms_post_action_add_to_cart_is_total_price'] = [
            'tab'         => 'content',
            'group'       => 'wcAddToCart',
            'type'       => 'checkbox',
            'label'       => esc_html__('Is Total Price', 'bricks'),
            'description' => esc_html__('If checked, the price will be the total price of the product. If the quantity is > 1, the price will be divided by the quantity, to get the price per unit.', 'bricks'),
        ];

        // Custom Fields Repeater
        $this->controls['pro_forms_post_action_add_to_cart_custom_fields'] = [
            'tab'           => 'content',
            'group'         => 'wcAddToCart',
            'label'         => esc_html__('Custom Fields', 'bricks'),
            'type'          => 'repeater',
            'titleProperty' => 'label',
            'fields'        => [
                'info' => [
                    'label'       => esc_html__('Info', 'bricks'),
                    'type'        => 'info',
                    'content' => esc_html__('Static Values, Field IDs or Dynamic Data', 'bricks'),
                ],

                'label' => [
                    'label' => esc_html__('Label', 'bricks'),
                    'type'  => 'text',
                    'description' => esc_html__('Example: Color', 'bricks'),
                ],
                'value' => [
                    'label' => esc_html__('Value', 'bricks'),
                    'type'  => 'text',
                    'description' => esc_html__('Example: Green', 'bricks'),
                ],
            ]
        ];

        // Consider Variations
        $this->controls['pro_forms_post_action_add_to_cart_consider_variations'] = [
            'tab'         => 'content',
            'group'       => 'wcAddToCart',
            'type'       => 'checkbox',
            'label'       => esc_html__('Consider Variations', 'bricks'),
            'description' => esc_html__('If your product is a variable product, you can use this option to consider the variations. Depending on the selected variation, the price of your variation will be used.', 'bricks'),
        ];

        // Consider Variations Info
        $this->controls['pro_forms_post_action_add_to_cart_consider_variations_info'] = [
            'tab'         => 'content',
            'group'       => 'wcAddToCart',
            'type'        => 'info',
            'content' => esc_html__('Make sure to add Custom Fields for each variation. Otherwise the variations will not be considered.', 'bricks'),
            'required' => [['pro_forms_post_action_add_to_cart_consider_variations', '=', true]]
        ];

        /**
         * Webhook
         */

        // Webhook URL

        // Repeater for multiple webhooks
        $this->controls['pro_forms_post_action_webhooks'] = [
            'tab'           => 'content',
            'group'         => 'webhook',
            'label'         => esc_html__('Webhooks', 'bricks'),
            'type'          => 'repeater',
            'titleProperty' => 'url',
            'fields'        => [
                'url' => [
                    'label' => esc_html__('URL', 'bricks'),
                    'type'  => 'text',
                    'description' => esc_html__('The URL of your webhook.', 'bricks'),
                ],
                'method' => [
                    'label' => esc_html__('HTTP Method', 'bricks'),
                    'type'  => 'select',
                    'options' => [
                        'POST' => 'POST',
                        'GET' => 'GET',
                        'PUT' => 'PUT',
                        'DELETE' => 'DELETE',
                        'PATCH' => 'PATCH',
                    ],
                    'description' => esc_html__('The HTTP method to use for the request.', 'bricks'),
                    'default' => 'POST'
                ],
                'content_type' => [
                    'label' => esc_html__('Content Type', 'bricks'),
                    'type'  => 'select',
                    'options' => [
                        'json' => 'JSON',
                        'form' => 'Form data',
                    ],
                    'description' => esc_html__('The content type of the data you want to send to your webhook.', 'bricks'),
                    'default' => 'json'
                ],
                'data' => [
                    'label' => esc_html__('Data', 'bricks'),
                    'type'  => 'repeater',
                    'description' => esc_html__('The data you want to send to your webhook. You can use form field IDs or dynamic data as well.', 'bricks'),
                    'titleProperty' => 'key',
                    'fields' => [
                        'key' => [
                            'label' => esc_html__('Key', 'bricks'),
                            'type'  => 'text',
                            'description' => esc_html__('The key of your data.', 'bricks'),
                        ],
                        'value' => [
                            'label' => esc_html__('Value', 'bricks'),
                            'type'  => 'text',
                            'description' => esc_html__('The value of your data.', 'bricks'),
                        ],
                    ],
                ],
                'headers' => [
                    'label' => esc_html__('Headers', 'bricks'),
                    'type'  => 'repeater',
                    'description' => esc_html__('The headers you want to send to your webhook. You can use form field IDs or dynamic data as well.', 'bricks'),
                    'titleProperty' => 'key',
                    'fields' => [
                        'key' => [
                            'label' => esc_html__('Key', 'bricks'),
                            'type'  => 'text',
                            'description' => esc_html__('The key of your header.', 'bricks'),
                        ],
                        'value' => [
                            'label' => esc_html__('Value', 'bricks'),
                            'type'  => 'text',
                            'description' => esc_html__('The value of your header.', 'bricks'),
                        ],
                    ],
                ],
                'add_hmac' => [
                    'label' => esc_html__('Add HMAC Header', 'bricks'),
                    'type'  => 'checkbox',
                    'description' => esc_html__('If you want to add a HMAC header to your webhook request, you can enable this option.', 'bricks'),
                ],
                'hmac_header_name' => [
                    'label' => esc_html__('HMAC Header Name', 'bricks'),
                    'type'  => 'text',
                    'description' => esc_html__('The header name for your HMAC header.', 'bricks'),
                    'required' => [['add_hmac', '=', true]]
                ],
                'hmac_key' => [
                    'label' => esc_html__('HMAC Secret Key', 'bricks'),
                    'type'  => 'text',
                    'description' => esc_html__('The secret key for your HMAC header.', 'bricks'),
                    'required' => [['add_hmac', '=', true]]
                ],
                'debug_show_response_in_console' => [
                    'label' => esc_html__('Debug: Show Response in Console', 'bricks'),
                    'type'  => 'checkbox',
                    'description' => esc_html__('If you want to show the response of your webhook request in your developer console, you can enable this option.', 'bricks'),
                ],
            ],
        ];


        // Update User Meta Group
        $this->controls['updateUserMetaInfo'] = [
            'tab'         => 'content',
            'group'       => 'updateUserMeta',
            'label'       => esc_html__('Info', 'bricks'),
            'type'        => 'info',
            'content' => esc_html__('You can use form field IDs or dynamic data as well.', 'bricks'),
        ];

        // Create repeater field "updateUserMetaData"
        $this->controls['pro_forms_post_action_update_user_meta_data'] = [
            'tab'           => 'content',
            'group'         => 'updateUserMeta',
            'label'         => esc_html__('User Meta Data', 'bricks'),
            'type'          => 'repeater',
            'titleProperty' => 'label',
            'fields'        => [
                'user_id' => [
                    'label' => esc_html__('User ID', 'bricks'),
                    'type'  => 'text',
                ],
                'key' => [
                    'label' => esc_html__('Meta Key', 'bricks'),
                    'type'  => 'text',
                ],
                'value' => [
                    'label' => esc_html__('Meta Value', 'bricks'),
                    'type'  => 'text',
                ],
                'type'         => [
                    'label'   => esc_html__('Update Type', 'bricks'),
                    'type'    => 'select',
                    'options' => [
                        'replace'             => esc_html__('Replace Value', 'bricks'),
                        'increment'           => esc_html__('Increment Number', 'bricks'),
                        'decrement'           => esc_html__('Decrement Number', 'bricks'),
                        'increment_by_number' => esc_html__('Increment by Number', 'bricks'),
                        'decrement_by_number' => esc_html__('Decrement by Number', 'bricks'),
                        'add_to_array'        => esc_html__('Add to Array', 'bricks'),
                        'remove_from_array'   => esc_html__('Remove from Array', 'bricks'),
                    ],
                    'default' => 'replace'
                ],
                'number_field' => [
                    'required'    => [['type', '=', ['increment_by_number', 'decrement_by_number']]],
                    'label'       => esc_html__('Select Number Field', 'bricks'),
                    'type'        => 'text',
                    'placeholder' => 'Enter Form Field ID',
                ],
                'ignore_empty' => [
                    'label'        => esc_html__('Ignore if empty', 'bricks'),
                    'type'         => 'checkbox',
                    'default'      => false,
                    'description'  => esc_html__('If checked, the post meta will not be updated when the form field value is empty', 'bricks'),
                ],
                'selector'     => [
                    'required'    => [['type', '!=', 'add_to_array'], ['type', '!=', 'remove_from_array']],
                    'label'       => esc_html__('Live Update Selector', 'bricks'),
                    'type'        => 'text',
                    'placeholder' => '.selector',
                    'description' => esc_html__('Enter a selector from the element you want to live change the value with the new value from the database', 'bricks'),
                ],
            ]
        ];


        $this->controls['disableSubmitMessage'] = [
            'tab'         => 'content',
            'group'       => 'other',
            'label'       => esc_html__('Disable Submit Messages', 'bricks'),
            'type'        => 'checkbox',
            'description' => esc_html__("If you don't want to show a submit messages, activate this setting", 'bricks'),
            'css'         => [
                [
                    'property'  => 'display',
                    'value'     => 'none',
                    'important' => true,
                    'selector'  => 'div.message.success',
                ],
                [
                    'property'  => 'display',
                    'value'     => 'none',
                    'important' => true,
                    'selector'  => 'div.message.error',
                ],
                [
                    'property'  => 'display',
                    'value'     => 'none',
                    'important' => true,
                    'selector'  => '.loading',
                ],
            ],
        ];

        $this->controls['disableFormReset'] = [
            'tab'         => 'content',
            'group'       => 'other',
            'label'       => esc_html__('Disable Form Reset', 'bricks'),
            'type'        => 'checkbox',
            'description' => esc_html__("If you don't want to reset the form after a successful submission, activate this setting", 'bricks'),
        ];

        // Reset User Password

        $this->controls['resetUserPasswordMethod'] = [
            'tab'         => 'content',
            'group'       => 'resetUserPassword',
            'label'       => esc_html__('Method', 'bricks'),
            'type'        => 'select',
            'options'     => [
                'request' => esc_html__('Reset Password Email (Recommended)', 'bricks'),
                'update'  => esc_html__('Update Password', 'bricks'),
            ],
            'description' => esc_html__('Select the method you want to use to reset the user password.', 'bricks'),
        ];

        $this->controls['resetUserPasswordEmail'] = [
            'tab' => 'content',
            'group' => 'resetUserPassword',
            'label' => esc_html__('User Email', 'bricks'),
            'type' => 'text',
            'placeholder' => 'Form Field ID'
        ];

        // New Password
        $this->controls['resetUserPasswordNewPassword'] = [
            'tab'         => 'content',
            'group'       => 'resetUserPassword',
            'label'       => esc_html__('New Password', 'bricks'),
            'type'        => 'text',
            'required'    => [['resetUserPasswordMethod', '=', 'update']],
        ];

        // Verify Password confirmation
        $this->controls['resetUserPasswordVerifyPasswordConfirmation'] = [
            'tab'         => 'content',
            'group'       => 'resetUserPassword',
            'label'       => esc_html__('Server Side: Verify Password Confirmation', 'bricks'),
            'type'        => 'checkbox',
            'description' => esc_html__('If you additionally want to verify the password, activate this setting.', 'bricks'),
            'required'    => [['resetUserPasswordMethod', '=', 'update']],
        ];

        // Password Confirmation
        $this->controls['resetUserPasswordPasswordConfirmationValue'] = [
            'tab'         => 'content',
            'group'       => 'resetUserPassword',
            'label'       => esc_html__('Password 2 (Confirmation Field)', 'bricks'),
            'type'        => 'text',
            'placeholder' => 'Form Field ID',
            'required'    => [['resetUserPasswordMethod', '=', 'update'], ['resetUserPasswordVerifyPasswordConfirmation', '=', true]],
        ];


        // Server Side: Allow only strong passwords
        $this->controls['resetUserPasswordAllowOnlyStrongPasswords'] = [
            'tab'         => 'content',
            'group'       => 'resetUserPassword',
            'label'       => esc_html__('Server Side: Allow only strong passwords', 'bricks'),
            'type'        => 'checkbox',
            'description' => esc_html__('If you want to allow only strong passwords, activate this setting.', 'bricks'),
            'required'    => [['resetUserPasswordMethod', '=', 'update']],
        ];

        // Verify current password
        $this->controls['resetUserPasswordVerifyCurrentPassword'] = [
            'tab'         => 'content',
            'group'       => 'resetUserPassword',
            'label'       => esc_html__('Server Side: Verify Current Password', 'bricks'),
            'type'        => 'checkbox',
            'description' => esc_html__('If you additionally want to verify the current password, activate this setting.', 'bricks'),
            'required'    => [['resetUserPasswordMethod', '=', 'update']],
        ];

        // Current Password
        $this->controls['resetUserPasswordCurrentPasswordValue'] = [
            'tab'         => 'content',
            'group'       => 'resetUserPassword',
            'label'       => esc_html__('Current Password', 'bricks'),
            'type'        => 'text',
            'placeholder' => 'Form Field ID',
            'required'    => [['resetUserPasswordMethod', '=', 'update'], ['resetUserPasswordVerifyCurrentPassword', '=', true]],
        ];

        // Notification "Please enter a new password"
        $this->controls['resetUserPasswordNotificationNewPassword'] = [
            'tab'         => 'content',
            'group'       => 'resetUserPassword',
            'label'       => esc_html__('Notification: Please enter new password', 'bricks'),
            'type'        => 'text',
            'default' => esc_html__('Please enter a new password', 'bricks'),
            'required'    => [['resetUserPasswordMethod', '=', 'update']],
        ];

        // Notification "Passwords do not match"
        $this->controls['resetUserPasswordNotificationPasswordsDoNotMatch'] = [
            'tab'         => 'content',
            'group'       => 'resetUserPassword',
            'label'       => esc_html__('Notification: Passwords do not match', 'bricks'),
            'type'        => 'text',
            'default' => esc_html__('Passwords do not match', 'bricks'),
            'required'    => [['resetUserPasswordMethod', '=', 'update'], ['resetUserPasswordVerifyPasswordConfirmation', '=', true]],
        ];

        // Notification "Current password is incorrect"
        $this->controls['resetUserPasswordNotificationCurrentPasswordIncorrect'] = [
            'tab'         => 'content',
            'group'       => 'resetUserPassword',
            'label'       => esc_html__('Notification: Current password is incorrect', 'bricks'),
            'type'        => 'text',
            'default' => esc_html__('Current password is incorrect', 'bricks'),
            'required'    => [['resetUserPasswordMethod', '=', 'update'], ['resetUserPasswordVerifyCurrentPassword', '=', true]],
        ];
    }

    public function get_post_types()
    {
        $post_types = get_post_types(['public' => true], 'objects');
        $options = [];

        foreach ($post_types as $post_type) {
            $options[$post_type->name] = $post_type->label;
        }

        return $options;
    }

    public function get_quill_formats()
    {
        return [
            'header' => 'Headlines',
            'bold'           => 'Bold',
            'italic'         => 'Italic',
            'underline'      => 'Underline',
            'color' => 'Color',
            'background'       => 'Background Color',
            'strike'         => 'Strikethrough',
            'link' => 'Link',
            'code' => 'Code',

            'blockquote'     => 'Blockquote',
            'indent' => 'Indent',
            'outdent' => 'Outdent',
            'orderedList' => 'Ordered List',
            'bulletList' => 'Bullet List',
            'align' => 'Text Alignment',
            'direction' => 'Text Direction',
            'code-block' => 'Code Block',

            'image' => 'Image',
            'video' => 'Video'
        ];
    }

    public function get_color_palettes()
    {

        $palettes = get_option(BRICKS_DB_COLOR_PALETTE, []);

        if (empty($palettes)) {
            $palettes = \Bricks\Builder::default_color_palette();
        }

        // Extract the "name" field for each palette in the array
        $palette_names = array_column($palettes, 'name');

        // Create an array with the palette names as keys and the palette names as values
        $palette_names = array_combine($palette_names, $palette_names);

        return $palette_names;
    }

    public function check_for_hcaptcha_keys()
    {
        $hcaptcha_settings = array_values(array_filter(get_option('brf_activated_elements'), function ($tool) {
            return $tool->id == 5;
        }));

        if (count($hcaptcha_settings) === 0) {
            return [false];
        }

        $hcaptcha_settings = $hcaptcha_settings[0];

        if (!isset($hcaptcha_settings->settings->useHCaptcha) || $hcaptcha_settings->settings->useHCaptcha !== true) {
            return [false];
        }

        if (empty($hcaptcha_settings->settings->hCaptchaKey) || empty($hcaptcha_settings->settings->hCaptchaSecret)) {
            return [false];
        }

        $utils = new \Bricksforge\Api\Utils();

        $decrypted_hcaptcha_key = $utils->decrypt($hcaptcha_settings->settings->hCaptchaKey);
        $decrypted_hcaptcha_secret = $utils->decrypt($hcaptcha_settings->settings->hCaptchaSecret);

        return [true, $decrypted_hcaptcha_key, $decrypted_hcaptcha_secret];
    }

    public function needs_init_rendering($settings)
    {
        if (isset($settings['actions']) && in_array('wc_add_to_cart', $settings['actions'])) {
            return true;
        }

        if (isset($settings['submitButtonHasCondition']) && $settings['submitButtonHasCondition'] == true) {
            return true;
        }

        return false;
    }

    public function autocomplete_options()
    {
        return [
            "off" => "off",
            "on" => "on",
            "name" => "name",
            "honorific-prefix" => "honorific-prefix",
            "given-name" => "given-name",
            "additional-name" => "additional-name",
            "family-name" => "family-name",
            "honorific-suffix" => "honorific-suffix",
            "nickname" => "nickname",
            "email" => "email",
            "username" => "username",
            "new-password" => "new-password",
            "current-password" => "current-password",
            "one-time-code" => "one-time-code",
            "organization-title" => "organization-title",
            "organization" => "organization",
            "street-address" => "street-address",
            "address-line1" => "address-line1",
            "address-line2" => "address-line2",
            "address-line3" => "address-line3",
            "address-level4" => "address-level4",
            "address-level3" => "address-level3",
            "address-level2" => "address-level2",
            "address-level1" => "address-level1",
            "country" => "country",
            "country-name" => "country-name",
            "postal-code" => "postal-code",
            "cc-name" => "cc-name",
            "cc-given-name" => "cc-given-name",
            "cc-additional-name" => "cc-additional-name",
            "cc-family-name" => "cc-family-name",
            "cc-number" => "cc-number",
            "cc-exp" => "cc-exp",
            "cc-exp-month" => "cc-exp-month",
            "cc-exp-year" => "cc-exp-year",
            "cc-csc" => "cc-csc",
            "cc-type" => "cc-type",
            "transaction-currency" => "transaction-currency",
            "transaction-amount" => "transaction-amount",
            "language" => "language",
            "bday" => "bday",
            "bday-day" => "bday-day",
            "bday-month" => "bday-month",
            "bday-year" => "bday-year",
            "sex" => "sex",
            "tel" => "tel",
            "tel-country-code" => "tel-country-code",
            "tel-national" => "tel-national",
            "tel-area-code" => "tel-area-code",
            "tel-local" => "tel-local",
            "tel-local-prefix" => "tel-local-prefix",
            "tel-local-suffix" => "tel-local-suffix",
            "tel-extension" => "tel-extension",
            "impp" => "impp",
            "url" => "url",
            "photo" => "photo",
            "webauthn" => "webauthn",
        ];
    }

    public function check_for_turnstile_keys()
    {
        $turnstile_settings = array_values(array_filter(get_option('brf_activated_elements'), function ($tool) {
            return $tool->id == 5;
        }));

        if (count($turnstile_settings) === 0) {
            return [false];
        }

        $turnstile_settings = $turnstile_settings[0];

        if (!isset($turnstile_settings->settings->useTurnstile) || $turnstile_settings->settings->useTurnstile !== true) {
            return [false];
        }

        if (empty($turnstile_settings->settings->turnstileKey)) {
            return [false];
        }

        $utils = new \Bricksforge\Api\Utils();
        $decrypted_turnstile_key = $utils->decrypt($turnstile_settings->settings->turnstileKey);

        $this->turnstile_key = $decrypted_turnstile_key;

        return [true, $decrypted_turnstile_key];
    }

    public function get_actions()
    {
        // Combine Integrations\Form\Init::get_available_actions() with two custom actions
        $actions = array_merge(
            Integrations\Form\Init::get_available_actions(),
            [
                'post_create'      => esc_html__('Create New Post', 'bricks'),
                'post_update' => esc_html__('Update Post', 'bricks'),
                'update_post_meta' => esc_html__('Update Post Meta', 'bricks'),
                'update_user_meta' => esc_html__('Update User Meta', 'bricks'),
                'reset_user_password' => esc_html__('Reset User Password', 'bricks'),
                'add_option'       => esc_html__('Database: Add Option', 'bricks'),
                'update_option'    => esc_html__('Database: Update Option', 'bricks'),
                'delete_option'    => esc_html__('Database: Delete Option', 'bricks'),
                'set_storage_item' => esc_html__('Set Storage Item', 'bricks'),
                'webhook'          => esc_html__('Webhook', 'bricks'),
            ]
        );

        // Add Submissions only if they are activated
        if (get_option('brf_activated_tools') && in_array(11, get_option('brf_activated_tools'))) {
            $actions['create_submission'] = esc_html__('Create Submission', 'bricks');
        }

        // If WooCommerce is active, add the WooCommerce actions
        if (class_exists('WooCommerce')) {
            $actions['wc_add_to_cart'] = esc_html__('WooCommerce: Add to Cart', 'bricks');
        }

        return $actions;
    }

    public function get_wc_products()
    {
        if (!class_exists('WooCommerce')) {
            return [];
        }

        $products = wc_get_products([
            'limit' => 100,
            'status' => 'publish',
        ]);

        if (empty($products)) {
            return [];
        }

        $product_options = [];

        // Add "custom"
        $product_options['custom'] = esc_html__('Custom', 'bricks');

        foreach ($products as $product) {
            $product_options[$product->get_id()] = $product->get_name();
        }

        return $product_options;
    }

    public function get_submit_conditions()
    {
        return [
            'option'                   => esc_html__('Database: Option', 'bricks'),
            'post_meta'                => esc_html__('Post Meta Field', 'bricks'),
            'storage_item'             => esc_html__('Storage Item', 'bricks'),
            'form_field'               => esc_html__('Form Field', 'bricks'),
            'submission_count_reached' => esc_html__('Submission Limit Reached', 'bricks'),
            'submission_field'         => esc_html__('Submission Field (ID)', 'bricks'),
        ];
    }

    public function get_field_conditions()
    {
        return [
            'form_field'               => esc_html__('Form Field', 'bricks'),
            'storage_item'             => esc_html__('Storage Item', 'bricks'),
        ];
    }

    public function get_condition_operators()
    {
        return [
            '=='           => esc_html__('Is Equal', 'bricks'),
            '!='           => esc_html__('Is Not Equal', 'bricks'),
            '>'            => esc_html__('Is Greater Than', 'bricks'),
            '>='           => esc_html__('Is Greater Than or Equal', 'bricks'),
            '<'            => esc_html__('Is Less Than', 'bricks'),
            '<='           => esc_html__('Is Less Than or Equal', 'bricks'),
            'contains'     => esc_html__('Contains', 'bricks'),
            'not_contains' => esc_html__('Not Contains', 'bricks'),
            'starts_with'  => esc_html__('Starts With', 'bricks'),
            'ends_with'    => esc_html__('Ends With', 'bricks'),
            'empty'        => esc_html__('Is Empty', 'bricks'),
            'not_empty'    => esc_html__('Is Not Empty', 'bricks'),
            'exists'       => esc_html__('Exists', 'bricks'),
            'not_exists'   => esc_html__('Not Exists', 'bricks'),
        ];
    }

    public function get_condition_data_types()
    {
        return [
            'string' => esc_html__('String', 'bricks'),
            'number' => esc_html__('Number', 'bricks'),
            'array'  => esc_html__('Array', 'bricks'),
        ];
    }

    public function has_children()
    {
        return isset($this->element['children']) && !empty($this->element['children']);
    }

    public function render()
    {
        $settings = $this->settings;

        if (empty($settings['fields'])) {
            // Add an input type hidden field as placeholder
            $settings['fields'][] = [
                'type' => 'hidden',
                'name' => 'brf_form_placeholder',
            ];
            /* return $this->render_element_placeholder(
                [
                    'title' => esc_html__('No form field added.', 'bricks'),
                ]
            ); */
        }

        // Fields using <input type="X" />
        $input_types = [
            'email',
            'number',
            'text',
            'tel',
            'url',
            'datepicker',
            'password',
            'file',
            'hidden',
            'step'
        ];

        $this->set_attribute('_root', 'method', 'post');

        if ($this->has_children() === true) {
            $this->set_attribute('_root', 'data-nestable', 'true');

            // If $this->element['children'] contains a field with type "step", add data attribute
            foreach ($this->element['children'] as $child) {
                $child_element = ElementsHelper::get_element_by_id($child);
                if (!isset($child_element)) {
                    continue;
                }

                // If child element name is brf-pro-forms-field-step, add data attribute and stop here
                if ($child_element['name'] === 'brf-pro-forms-field-step') {
                    $this->set_attribute('_root', 'data-multistep', 'true');
                    break;
                }
            }
        }

        /**
         * Bricksforge Root Data
         */

        // Add Class to form tag to match styling
        $this->set_attribute('_root', 'class', 'brxe-form');

        // Add class if is a multi step form
        if (in_array('step', array_column($settings['fields'], 'type'))) {
            $this->set_attribute('_root', 'class', 'brxe-form-multistep');
        }

        // Add Attribute if has submit conditions
        if (isset($settings['submitButtonHasCondition']) && $settings['submitButtonHasCondition']) {
            $this->set_attribute('_root', 'data-brf-submit-conditions', 'true');
        }

        // Add Attribute if disable form reset is set to true
        if (isset($settings['disableFormReset']) && $settings['disableFormReset']) {
            $this->set_attribute('_root', 'data-brf-disable-reset', 'true');
        }

        // Add Attribute if includes a summary
        if (isset($settings['multiStepSummary']) && $settings['multiStepSummary']) {
            $this->set_attribute('_root', 'data-brf-summary', 'true');


            // If multiStepSummaryShowEmpty is true, add an attribute data-brf-summary-empty to the form
            if (isset($settings['multiStepSummaryShowEmpty']) && $settings['multiStepSummaryShowEmpty']) {
                $this->set_attribute('_root', 'data-brf-summary-empty', 'true');
            }

            // If multiStepSummaryShowEmpty is true, add an attribute data-brf-summary-empty-text to the form
            if (isset($settings['multiStepSummaryEmptyText']) && $settings['multiStepSummaryEmptyText']) {
                $this->set_attribute('_root', 'data-brf-summary-empty-text', $settings['multiStepSummaryEmptyText']);
            }

            // Add the field multiStepSummaryMainHeadline as data attribute
            if (isset($settings['multiStepSummaryMainHeadline']) && $settings['multiStepSummaryMainHeadline']) {
                $this->set_attribute('_root', 'data-brf-summary-headline', $settings['multiStepSummaryMainHeadline']);
            }
        }

        if ($this->needs_init_rendering($settings) === true) {
            $this->set_attribute('_root', 'data-brf-needs-rendering', 'true');
        }

        // Visual Steps
        if (isset($settings['multiStepShowSteps']) && $settings['multiStepShowSteps']) {
            $this->set_attribute('_root', 'data-brf-show-steps', 'true');

            if (isset($settings['multiStepFirstStep']) && $settings['multiStepFirstStep']) {
                $this->set_attribute('_root', 'data-brf-first-step', $settings['multiStepFirstStep']);
            }

            if (isset($settings['multiStepStepAllowClicks']) && $settings['multiStepStepAllowClicks']) {
                $this->set_attribute('_root', 'data-brf-step-allow-clicks', 'true');
            }
        }

        // If checkbox has custom style, add data attribute
        if (isset($settings['checkboxCustomStyle']) && $settings['checkboxCustomStyle']) {
            $this->set_attribute("_root", 'data-checkbox-custom');
        }

        // If checkbox is checkboxCard, add data attribute
        if (isset($settings['checkboxCard']) && $settings['checkboxCard']) {
            $this->set_attribute("_root", 'data-checkbox-card');
        }

        // If radio has custom style, add data attribute
        if (isset($settings['radioCustomStyle']) && $settings['radioCustomStyle']) {
            $this->set_attribute("_root", 'data-radio-custom');
        }

        // If radio is radioCard, add data attribute
        if (isset($settings['radioCard']) && $settings['radioCard']) {
            $this->set_attribute("_root", 'data-radio-card');
        }

        // If "pro_forms_post_action_update_post_meta_data" is not empty, loop and check if the field post_id is set
        if (isset($settings['pro_forms_post_action_update_post_meta_data']) && !empty($settings['pro_forms_post_action_update_post_meta_data'])) {
            $ids = array();
            foreach ($settings['pro_forms_post_action_update_post_meta_data'] as $index => $post_meta) {
                if (isset($post_meta['post_id'])) {
                    $ids[] = $post_meta['post_id'];
                }
            }
            if (!empty($ids)) {
                $this->set_attribute('_root', 'data-brf-dynamic-post-id', $ids[0]);
            }
        }


        // We need the form element ID to recover the element settings on form submit
        $this->set_attribute('_root', 'data-element-id', $this->id);

        $this->set_attribute('enctype', 'method', 'multipart/form-data');

        foreach ($settings['fields'] as $index => $field) {
            // Field ID generated when rendering form repeater in builder panel
            $field_id = isset($field['id']) ? $field['id'] : '';

            // Add Conditional Logic Data to field
            if (isset($field['hasConditions']) && isset($field['conditions']) && $field['conditions']) {
                $this->set_attribute("field-wrapper-$index", 'data-brf-conditions', json_encode($field['conditions']));
            }

            // Add conditionsRelation as well
            if (isset($field['conditionsRelation']) && $field['conditionsRelation']) {
                $this->set_attribute("field-wrapper-$index", 'data-brf-conditions-relation', $field['conditionsRelation']);
            }

            // Add CSS Class if needed
            if (isset($field['cssClass']) && $field['cssClass']) {
                // Split on space
                $classes = explode(' ', $field['cssClass']);
                // Add each class
                foreach ($classes as $class) {
                    $this->set_attribute("field-wrapper-$index", 'class', $class);
                }
            }

            // If has 'ignoreCustomStyles', add data attribute
            if (isset($field['ignoreCustomStyles']) && $field['ignoreCustomStyles']) {
                $this->set_attribute("field-wrapper-$index", 'data-ignore-custom-styles');
            }

            // If has Max Length, add it as data attribute to textarea or input
            if (isset($field['maxLength']) && $field['maxLength']) {
                $this->set_attribute("field-$index", 'maxlength', $field['maxLength']);
            }

            // Get a unique field ID to avoid conflicts when the form is inside a query loop or it was duplicated
            $input_unique_id = Helpers::generate_random_id(false);

            // Field wrapper
            if ($field['type'] !== 'hidden' && $field['type'] !== 'step' && $field['type'] !== 'groupStart' && $field['type'] !== 'groupEnd') {
                $this->set_attribute("field-wrapper-$index", 'class', ['form-group', $field['type'] === 'file' ? 'file' : '']);

                // Add ID as data attribute
                $this->set_attribute("field-wrapper-$index", 'data-field-id', $field['id']);

                // Add field type as data attribute
                $this->set_attribute("field-wrapper-$index", 'data-field-type', $field['type']);
            }

            // Groups
            if ($field['type'] === 'groupStart') {
                $this->set_attribute("field-wrapper-$index", 'class', ['is-group', 'group-start']);

                // If has a css class, add it as well
                if (isset($field['cssClass']) && $field['cssClass']) {
                    // Split on space
                    $classes = explode(' ', $field['cssClass']);
                    // Add each class
                    foreach ($classes as $class) {
                        $this->set_attribute("field-wrapper-$index", 'class', $class);
                    }
                }
            }

            if ($field['type'] === 'groupEnd') {
                $this->set_attribute("field-wrapper-$index", 'class', ['is-group', 'group-end']);
            }

            // Heading
            if ($field['type'] === 'heading') {
                $this->set_attribute("field-wrapper-$index", 'class', 'brf-field-heading-wrapper');
            }

            // File Upload
            if (isset($field['hideImagePreview']) && $field['hideImagePreview'] === true) {
                $this->set_attribute("field-$index", 'data-hide-image-preview', 'true');
            }

            // Rich Text
            if ($field['type'] === 'rich-text') {
                $this->set_attribute("field-wrapper-$index", 'class', 'brf-field-rich-text');

                // Store the quill style in a data attribute
                if (isset($field['quillStyle']) && !empty($field['quillStyle'])) {
                    $this->set_attribute("field-wrapper-$index", 'data-theme', $field['quillStyle']);
                }

                // Store the placeholder in a data attribute
                if (isset($field['placeholder']) && !empty($field['placeholder'])) {
                    $this->set_attribute("field-wrapper-$index", 'data-placeholder', $field['placeholder']);
                }

                // Store the read only state (quillReadOnly as checkbox) in a data attribute
                if (isset($field['quillReadOnly']) && !empty($field['quillReadOnly'])) {
                    $this->set_attribute("field-wrapper-$index", 'data-readonly', "true");
                }

                // Store the formats in a data attribute (quillFormats). Store it comma separated
                if (isset($field['quillFormats']) && !empty($field['quillFormats'])) {
                    $this->set_attribute("field-wrapper-$index", 'data-formats', implode(',', $field['quillFormats']));
                }

                // Store the formats in a data attribute (mceFormats). Store it comma separated
                if (isset($field['quillStyle']) && $field['quillStyle'] == 'wordpress' && isset($field['mceFormats']) && !empty($field['mceFormats'])) {
                    $this->set_attribute("field-wrapper-$index", 'data-formats-mce', $field['mceFormats']);
                }

                // If the field quillUseBricksColors is set and quillColorPalette is set, store the colors in a data attribute
                if (isset($field['quillUseBricksColors']) && !empty($field['quillUseBricksColors']) && isset($field['quillBricksColorPalette']) && !empty($field['quillBricksColorPalette'])) {
                    $palette = get_option(BRICKS_DB_COLOR_PALETTE, []);

                    // Get the palette with the key "name" of the field quillBricksColorPalette
                    $palette = array_filter($palette, function ($item) use ($field) {
                        return $item['name'] === $field['quillBricksColorPalette'];
                    });

                    if (empty($palette)) {
                        $palette = \Bricks\Builder::default_color_palette();
                    }

                    // If the palette is found, store the colors in a data attribute
                    if (!empty($palette)) {

                        $colors_hex = array_reduce($palette, function ($result, $palette) {
                            $hex_values = array_map(function ($color) {
                                if (isset($color['hex'])) {
                                    return $color['hex'];
                                }
                            }, $palette['colors']);
                            return array_merge($result, $hex_values);
                        }, []);

                        $colors_string = implode(',', $colors_hex);

                        $this->set_attribute("field-wrapper-$index", 'data-colors', $colors_string);
                    }
                }
            }

            if ($field['type'] == 'step') {
                $this->set_attribute("field-wrapper-$index", 'class', ['step']);
                $this->set_attribute("field-wrapper-$index", 'aria-label', isset($field['label']) ? $field['label'] : '');
            }

            // If field type is calculation, create a div with the class "calculation"
            if ($field['type'] == 'calculation') {
                $this->set_attribute("field-wrapper-$index", 'class', ['calculation-field']);
                $this->set_attribute("calculation-$index", 'aria-label', isset($field['label']) && !empty($field['label']) ? $field['label'] : '');
                $this->set_attribute("field-wrapper-$index", 'data-empty-message', isset($field['emptyMessage']) ? $field['emptyMessage'] : '');
            }

            // Field label
            if ($field['type'] !== 'checkbox' && $field['type'] !== 'radio') {
                $this->set_attribute("label-$index", 'for', "form-field-{$input_unique_id}");
            }


            if ($field['type'] === 'file') {
                if (!isset($field['fileUploadLimit']) || $field['fileUploadLimit'] > 1) {
                    $this->set_attribute("field-$index", 'multiple');
                }

                if (!empty($field['fileUploadLimit'])) {
                    $this->set_attribute("field-$index", 'data-limit', $field['fileUploadLimit']);
                }

                if (isset($field['fileUploadAllowedTypes'])) {
                    $types = str_replace('.', '', strtolower($field['fileUploadAllowedTypes']));
                    $types = array_map('trim', explode(',', $types));

                    if (in_array('jpg', $types) && !in_array('jpeg', $types)) {
                        $types[] = 'jpeg';
                    }

                    array_walk(
                        $types,
                        function (&$value) {
                            $value = '.' . $value;
                        }
                    );

                    $this->set_attribute("field-$index", 'accept', implode(',', $types));
                }

                if (!empty($field['fileUploadSize'])) {
                    $this->set_attribute("field-$index", 'data-maxsize', $field['fileUploadSize']);
                }

                // Link the input file to the file preview using a unique ID (the field ID could be duplicated)
                $this->set_attribute("field-$index", 'data-files-ref', $input_unique_id);

                $this->set_attribute("file-preview-$index", 'data-files-ref', $input_unique_id);
            }

            if (isset($settings['requiredAsterisk']) && isset($field['required'])) {
                $this->set_attribute("label-$index", 'class', 'required');
            }

            // Datepicker
            if ($field['type'] === 'datepicker') {
                $this->set_attribute("field-$index", 'class', 'flatpickr');

                $time_24h = get_option('time_format');
                $time_24h = strpos($time_24h, 'H') !== false || strpos($time_24h, 'G') !== false;

                $date_format = isset($field['time']) ? get_option('date_format') . ' H:i' : get_option('date_format');

                if (isset($field['dateFormat']) && $field['dateFormat']) {
                    $date_format = $field['dateFormat'];
                }

                $datepicker_options = [
                    // 'allowInput' => true,
                    'enableTime' => isset($field['time']),
                    'minTime'    => isset($field['minTime']) ? $field['minTime'] : '',
                    'maxTime'    => isset($field['maxTime']) ? $field['maxTime'] : '',
                    'altInput'   => true,
                    'altFormat'  => $date_format,
                    'dateFormat' => 'Y-m-d H:i',
                    'time_24hr'  => $time_24h,
                    'mode' => isset($field['dateRange']) ? 'range' : 'single',
                ];

                // If "Enable Dates" is set, add the dates to the options
                if (isset($field['needsEnableDates']) && $field['needsEnableDates'] && isset($field['enableDatesSource']) && $field['enableDatesSource'] === 'custom' && isset($field['enableDates']) && !empty($field['enableDates'])) {
                    $dates = array_map(function ($date) {
                        return isset($date['to']) ? ['from' => $date['from'], 'to' => $date['to']] : $date['from'];
                    }, $field['enableDates']);

                    $datepicker_options['enable'] = $dates;
                } elseif (isset($field['needsEnableDates']) && field['needsEnableDates'] && isset($field['enableDatesSource']) && $field['enableDatesSource'] === 'dynamic') {
                    if (isset($field['enableDatesDynamic']) && !empty($field['enableDatesDynamic'])) {
                        $datepicker_options['enable'] = bricks_render_dynamic_data($field['enableDatesDynamic']);
                    }
                }

                // If Enable Weekdays is set, add data attribute "enable-weekdays"
                if (isset($field['needsEnableWeekdays']) && $field['needsEnableWeekdays'] && isset($field['enableWeekdaysData']) && !empty($field['enableWeekdaysData'])) {
                    $this->set_attribute("field-$index", 'data-enable-weekdays', $field['enableWeekdaysData']);
                }

                // If "Disable Dates" is set, add the dates to the options
                if (isset($field['needsDisableDates']) && $field['needsDisableDates'] && isset($field['disableDatesSource']) && $field['disableDatesSource'] === 'custom' && isset($field['disableDates']) && !empty($field['disableDates'])) {
                    $dates = array_map(function ($date) {
                        return isset($date['to']) ? ['from' => $date['from'], 'to' => $date['to']] : $date['from'];
                    }, $field['disableDates']);

                    $datepicker_options['disable'] = $dates;
                } elseif (isset($field['needsDisableDates']) && field['needsDisableDates'] && isset($field['disableDatesSource']) && $field['disableDatesSource'] === 'dynamic') {
                    if (isset($field['disableDatesDynamic']) && !empty($field['disableDatesDynamic'])) {
                        $datepicker_options['disable'] = bricks_render_dynamic_data($field['disableDatesDynamic']);
                    }
                }

                // If showVisualCalendar is set, add the "inline" option
                if (isset($field['showVisualCalendar']) && $field['showVisualCalendar']) {
                    $datepicker_options['inline'] = true;
                }

                // Localization: https://flatpickr.js.org/localization/ (@since 1.8.6)
                if (!empty($field['l10n'])) {
                    $datepicker_options['locale'] = $field['l10n'];
                }

                // @see: https://academy.bricksbuilder.io/article/form-element/#datepicker
                $datepicker_options = apply_filters('bricks/element/form/datepicker_options', $datepicker_options, $this);

                $this->set_attribute("field-$index", 'data-bricks-datepicker-options', wp_json_encode($datepicker_options));
            }

            // Number min/max
            if ($field['type'] === 'number') {
                if (isset($field['min'])) {
                    $this->set_attribute("field-$index", 'min', $field['min']);
                }

                if (isset($field['max'])) {
                    $this->set_attribute("field-$index", 'max', $field['max']);
                }
            }

            $this->set_attribute("field-$index", 'id', "form-field-{$input_unique_id}");
            $this->set_attribute("field-$index", 'name', "form-field-{$field_id}");

            if (!isset($settings['showLabels']) && !empty($field['label']) && $field['type'] != 'hidden') {
                $this->set_attribute("field-$index", 'aria-label', $field['label']);
            }

            // Input types type & value
            if (in_array($field['type'], $input_types)) {
                $field_type = $field['type'] == 'datepicker' ? 'text' : $field['type'];

                if ($field['type'] === 'step') {
                    $this->set_attribute("field-$index", 'type', 'hidden');
                } else {
                    $this->set_attribute("field-$index", 'type', $field_type);
                }

                // Hidden field value
                if ($field['type'] === 'hidden' && isset($field['value'])) {
                    $this->set_attribute("field-$index", 'value', $field['value']);
                } elseif ($field['type'] !== 'file') {
                    // The type=file do not support value
                    $this->set_attribute("field-$index", 'value', '');
                }
            }

            $placeholder_support = [
                'email',
                'number',
                'text',
                'tel',
                'url',
                'datepicker',
                'password',
                'textarea'
            ];

            // Placeholder
            if (in_array($field['type'], $placeholder_support)) {
                if (isset($field['placeholder'])) {
                    if (isset($settings['requiredAsterisk']) && isset($field['required']) && (!isset($settings['showLabels']) || $settings['showLabels'] == false)) {
                        $field['placeholder'] = $field['placeholder'] . ' *';
                    }

                    $this->set_attribute("field-$index", 'placeholder', $field['placeholder']);
                }
            }

            // Turn off spell check for input and textarea
            if ($field['type'] === 'text' || $field['type'] === 'textarea') {
                $this->set_attribute("field-$index", 'spellcheck', 'false');
            }

            // Autocomplete for inputs
            if ($field['type'] === 'text' || $field['type'] === 'email' || $field['type'] === 'tel' || $field['type'] === 'url' || $field['type'] === 'number') {
                $autocomp_value = isset($field['autocomplete']) ? $field['autocomplete'] : 'off';
                $this->set_attribute("field-$index", 'autocomplete', $autocomp_value);
            }

            if (isset($field['required'])) {
                $this->set_attribute("field-$index", 'required');
            }

            if ($field['type'] == 'turnstile' && isset($settings['turnstileAppearance']) && $settings['turnstileAppearance'] != 'always') {
                $this->set_attribute("field-wrapper-$index", 'data-turnstile-hidden', "true");
            }
        }

        // Submit button
        $submit_button_icon_position = !empty($settings['submitButtonIconPosition']) ? $settings['submitButtonIconPosition'] : 'right';

        $this->set_attribute('submit-wrapper', 'class', ['form-group', 'submit-button-wrapper']);

        // If submitButtonConditionAction is "disabled", add the "disabled" attribute to the submit button
        if (!empty($settings['submitButtonConditionAction']) && $settings['submitButtonConditionAction'] === 'disabled') {
            $this->set_attribute('submit-wrapper', 'disabled');
        }

        $submit_button_classes[] = 'bricks-button';

        if (!empty($settings['submitButtonStyle']) && $this->has_children() === false) {
            $submit_button_classes[] = "bricks-background-{$settings['submitButtonStyle']}";
        }

        if (!empty($settings['submitButtonSize']) && $this->has_children() === false) {
            $submit_button_classes[] = $settings['submitButtonSize'];
        }

        if (isset($settings['submitButtonCircle'])) {
            $submit_button_classes[] = 'circle';
        }

        if (!empty($settings['submitButtonIcon'])) {
            $submit_button_classes[] = "icon-$submit_button_icon_position";
        }

        if (isset($settings['submitButtonClass']) && !empty($settings['submitButtonClass'])) {
            $submit_button_classes[] = $settings['submitButtonClass'];
        }

        $this->set_attribute('submit-button', 'class', $submit_button_classes);

        // Field Icons Attributes
        $this->set_attribute("field-icons", 'class', 'input-icon-wrapper');
        $this->set_attribute("field-icons", 'class', isset($settings['iconPosition']) && $settings['iconPosition'] == 'row' ? 'icon-left' : 'icon-right');

        if (isset($settings['iconInset']) && $settings['iconInset'] == true) {
            $this->set_attribute("field-icons", 'class', 'icon-inset');
        }

        if (isset($settings['iconFocusInput']) && $settings['iconFocusInput'] == true) {
            $this->set_attribute("field-icons", 'data-focus', 'true');
        }

?>
        <form <?php echo $this->render_attributes('_root'); ?>>
            <?php

            // Enqueue Builder Styles
            if (bricks_is_builder() || bricks_is_rest_call()) {
                echo $this->enqueue_form_styles($settings['fields']);
            }

            ?>
            <?php foreach ($settings['fields'] as $index => $field) { ?>
                <div <?php echo $this->render_attributes("field-wrapper-$index"); ?>>

                    <?php
                    $init_value = '';
                    // Check for Field Init Values
                    if (isset($field['initValue'])) {
                        $init_value = bricks_render_dynamic_data($field['initValue']);

                        // Strip dangerous html tags in wordpress way if is rich text field. If is not rich test, we want to strip all
                        if ($field['type'] === 'rich-text') {
                            $init_value = wp_kses_post($init_value);

                            // Convert double quotes to single quotes
                            $init_value = str_replace('"', "'", $init_value);
                        } else {
                            $init_value = wp_strip_all_tags($init_value);
                        }
                    }

                    if (isset($settings['showLabels']) && isset($field['label']) && $field['type'] !== 'hidden' && $field['type'] !== 'heading' && $field['type'] !== 'divider' && $field['type'] !== 'turnstile' && $field['type'] !== 'shortcode' && $field['type'] !== 'groupStart' && $field['type'] !== 'groupEnd') {
                        if ($field['type'] !== 'calculation' || empty($field['onlyRemote'])) {
                            $field_label = isset($field['label']) ? $field['label'] : '';
                            echo "<label {$this->render_attributes("label-$index")}>{$field_label}</label>";
                        }
                    } elseif (in_array($field['type'], ['checkbox', 'radio']) && !empty($field['placeholder'])) {
                        echo "<label {$this->render_attributes("label-$index")}>{$field['placeholder']}</label>";
                    }
                    ?>

                    <?php if (in_array($field['type'], $input_types)) { ?>
                        <?php if ($field['type'] == 'hidden') { ?>
                            <input value="<?php echo isset($field['value']) && $field['value'] ? $field['value'] : '' ?>" <?php echo $this->render_attributes("field-$index"); ?>>
                        <?php } else { ?>
                            <?php if (isset($field['icon'])) { ?>
                                <div <?php echo $this->render_attributes("field-icons"); ?>>
                                    <span class="input-icon"><?php echo $this->render_icon($field['icon']) ?></span>
                                    <input value="<?php echo $init_value ?>" <?php echo $this->render_attributes("field-$index"); ?>>
                                </div>
                            <?php } else { ?>
                                <input value="<?php echo $init_value ?>" <?php echo $this->render_attributes("field-$index"); ?>>
                            <?php } ?>
                        <?php } ?>
                    <?php } ?>

                    <?php if ($field['type'] === 'rich-text') { ?>
                        <?php $rich_style = isset($field['quillStyle']) ? $field['quillStyle'] : 'snow'; ?>
                        <?php if ($rich_style == 'wordpress') { ?>
                            <textarea class="brf-rich-text-container" <?php echo $this->render_attributes("field-$index"); ?>><?php echo $init_value ?></textarea>
                        <?php } else { ?>
                            <div class="brf-rich-text-container">
                                <?php echo $init_value ?>
                            </div>
                        <?php } ?>
                        <input data-type="rich-text" type="hidden" value="<?php echo $init_value ?>" <?php echo $this->render_attributes("field-$index"); ?>>
                    <?php } ?>

                    <?php if ($field['type'] === 'turnstile' && isset($settings['enableTurnstile']) && $settings['enableTurnstile'] && isset($this->turnstile_key)) { ?>
                        <div class="cf-turnstile" data-language="<?php echo isset($settings['turnstileLanguage']) ? $settings['turnstileLanguage'] : 'auto' ?>" data-appearance="<?php echo isset($settings['turnstileAppearance']) ? $settings['turnstileAppearance'] : 'always' ?>" data-size="<?php echo isset($settings['turnstileSize']) ? $settings['turnstileSize'] : 'normal' ?>" data-theme="<?php echo isset($settings['turnstileTheme']) ? $settings['turnstileTheme'] : 'light' ?>" data-sitekey="<?php echo isset($this->turnstile_key) ? $this->turnstile_key : '' ?>"></div>
                    <?php } ?>

                    <?php if ($field['type'] === 'calculation') { ?>
                        <div <?php echo $this->render_attributes("calculation-$index"); ?>>
                            <input readonly type="<?php echo isset($field['onlyRemote']) && $field['onlyRemote'] ? 'hidden' : 'text' ?>" class="calculation-result" value="0" <?php echo $this->render_attributes("field-$index"); ?>>
                        </div>
                    <?php } ?>

                    <?php if ($field['type'] === 'heading') { ?>
                        <?php
                        $heading_tag = isset($field['headingTag']) ? $field['headingTag'] : 'h3';
                        ?>
                        <<?php echo $heading_tag ?> <?php echo $this->render_attributes("heading-$index"); ?> class="brf-field-heading"><?php echo isset($field['label']) ? $field['label'] : '' ?></<?php echo $heading_tag ?>>

                        <?php if (isset($field['headingAddDescription']) && $field['headingAddDescription']) { ?>
                            <p <?php echo $this->render_attributes("heading-description-$index"); ?> class="brf-field-heading-description"><?php echo isset($field['headingDescription']) ? $field['headingDescription'] : '' ?></p>
                        <?php } ?>

                    <?php } ?>

                    <?php if ($field['type'] === 'divider') { ?>
                        <hr <?php echo $this->render_attributes("divider-$index"); ?> class="brf-field-divider">
                    <?php } ?>

                    <?php
                    if ($field['type'] == 'file') {
                        $label = isset($field['fileUploadButtonText']) ? $field['fileUploadButtonText'] : esc_html__('Choose files', 'bricks');

                        $this->set_attribute("file-preview-$index", 'class', 'file-result');
                        $this->set_attribute("file-preview-$index", 'data-error-limit', esc_html__('File %s not accepted. File limit exceeded.', 'bricks'));
                        $this->set_attribute("file-preview-$index", 'data-error-size', esc_html__('File %s not accepted. Size limit exceeded.', 'bricks'));

                        $this->set_attribute("label-$index", 'class', 'choose-files');
                    ?>

                        <?php if (isset($field['hideFileNamePreview']) && $field['hideFileNamePreview'] == true) { ?>
                            <?php $this->set_attribute("file-preview-$index", 'class', 'brf-hidden'); ?>
                        <?php } ?>

                        <div <?php echo $this->render_attributes("file-preview-$index"); ?>>
                            <span class="text"></span>
                            <button type="button" class="bricks-button remove">
                                <?php esc_html_e('Remove', 'bricks'); ?>
                            </button>
                        </div>

                        <?php
                        $file_init_value = bricks_render_dynamic_data(bricks_render_dynamic_data($init_value));
                        if ($file_init_value && !empty($file_init_value) && $file_init_value != "") {
                            echo '<div class="brf-field-image-preview">';
                            echo '<img src="' . $file_init_value . '" alt="Image Preview" />';
                            echo '<button type="button" class="bricks-button remove">';
                            echo esc_html_e('Remove', 'bricks');
                            echo '</button>';
                            echo '</div>';
                        }
                        ?>

                        <label <?php echo $this->render_attributes("label-$index"); ?>><?php echo $label; ?></label>
                    <?php } ?>

                    <?php if ($field['type'] === 'textarea') { ?>
                        <textarea <?php echo $this->render_attributes("field-$index"); ?>><?php echo $init_value ?></textarea>
                    <?php } ?>

                    <?php
                    if ($field['type'] === 'shortcode' && isset($field['shortcode'])) {

                        // If the shortcode contains "bricks_template", we need to load also the related css styles
                        if (strpos($field['shortcode'], 'bricks_template') !== false) {
                            // Get template ID from the format [bricks_template id="3936"]
                            $template_id = preg_replace('/[^0-9]/', '', $field['shortcode']);

                            if (class_exists('Bricks\Templates') && class_exists('Bricks\Frontend')) {

                                $elements = get_post_meta($template_id, BRICKS_DB_PAGE_CONTENT, true);
                                $inline_css = \Bricks\Templates::generate_inline_css($template_id, $elements);

                                // NOTE: Not the perfect solution – but currently the way to go.
                                echo "<style id=\"bricks-inline-css-template-{$template_id}\">{$inline_css}</style>";
                            }
                        }

                        echo do_shortcode($field['shortcode']);
                    }
                    ?>

                    <?php if ($field['type'] === 'select' && !empty($field['options'])) : ?>
                        <?php


                        ?>
                        <select <?php echo $this->render_attributes("field-$index"); ?>>
                            <?php

                            // If contains echo:, explode in another way
                            if (strpos($field['options'], 'echo:') !== false) {
                                $select_options = explode("\n", bricks_render_dynamic_data($field['options']));
                            } else {
                                $select_options = explode("\n", $field['options']);
                            }

                            $select_placeholder = false;

                            if (isset($field['placeholder'])) {
                                $select_placeholder = $field['placeholder'];

                                if (isset($settings['requiredAsterisk']) && isset($field['required']) && (!isset($settings['showLabels']) || $settings['showLabels'] == false)) {
                                    $select_placeholder .= ' *';
                                }

                                echo '<option value="" class="placeholder">' . $select_placeholder . '</option>';
                            }
                            ?>
                            <?php foreach ($select_options as $select_option) : ?>
                                <?php
                                $option_parts = explode('|', $select_option);
                                $option_key = isset($option_parts[0]) ? esc_attr($option_parts[0]) : '';
                                $option_value = isset($option_parts[1]) ? esc_html($option_parts[1]) : $option_key;

                                $init_value = bricks_render_dynamic_data(bricks_render_dynamic_data($init_value));

                                ?>
                                <option data-label="<?php echo $option_value; ?>" value="<?php echo $option_key; ?>" <?php echo $init_value === $option_key ? "selected" : "" ?>><?php echo $option_value; ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>

                    <?php if (!empty($field['options']) && ($field['type'] === 'checkbox' || $field['type'] === 'radio')) { ?>
                        <ul class="options-wrapper">
                            <?php

                            if (strpos($field['options'], 'echo:') !== false) {
                                $options = explode("\n", bricks_render_dynamic_data($field['options']));
                            } else {
                                $options = explode("\n", $field['options']);
                            }

                            ?>
                            <?php foreach ($options as $key => $value) { ?>
                                <?php
                                $option_parts = explode('|', $value);
                                $option_key = isset($option_parts[0]) ? $option_parts[0] : '';
                                $option_value = isset($option_parts[1]) ? $option_parts[1] : $option_key;
                                ?>
                                <li>
                                    <?php
                                    // Split the initial value into an array if it contains multiple values
                                    $init_values = explode(", ", $init_value);
                                    ?>
                                    <input data-label="<?php echo esc_html($option_value); ?>" <?php echo in_array($option_key, $init_values) ? "checked" : "" ?> type="<?php echo esc_attr($field['type']); ?>" id="<?php echo esc_attr("form-field-{$field['id']}") . '-' . $key; ?>" name="<?php echo esc_attr("form-field-{$field['id']}"); ?>[]" <?php if (isset($field['required'])) {
                                                                                                                                                                                                                                                                                                                                                            echo esc_attr('required');
                                                                                                                                                                                                                                                                                                                                                        } ?> value="<?php echo esc_html($option_key); ?>">
                                    <label for="<?php echo esc_attr("form-field-{$field['id']}") . '-' . $key; ?>"><?php echo $option_value; ?></label>
                                </li>
                            <?php } ?>
                        </ul>
                    <?php } ?>


                </div>
            <?php
            }

            // Submit button icon
            $submit_button_icon = isset($settings['submitButtonIcon']) ? self::render_icon($settings['submitButtonIcon']) : false;

            // Reload SVG
            $loading_svg;
            if (version_compare(BRICKS_VERSION, '1.8.1', '<')) {
                $loading_svg = Helpers::get_file_contents(BRICKS_PATH_ASSETS . 'svg/frontend/reload.svg');
            } else {
                $loading_svg = Helpers::file_get_contents(BRICKS_PATH_ASSETS . 'svg/frontend/reload.svg');
            }
            ?>

            <?php echo Frontend::render_children($this); ?>

            <?php if (isset($settings['showNotificationsInBuilder']) && $settings['showNotificationsInBuilder'] == true && (bricks_is_builder() || bricks_is_builder_call())) { ?>
                <div class="message success">
                    <div class="text">Message successfully sent. We will get back to you as soon as possible.</div>
                </div>
                <div class="message error">
                    <div class="text">An error occurred. Please try again later.</div>
                </div>
            <?php } ?>


            <?php if (isset($settings['multiStepSummary']) && $settings['multiStepSummary'] && $this->has_children() == false) { ?>
                <button type="button" class="bricks-button summary">
                    <?php esc_html_e(isset($settings['multiStepSummaryButtonText']) && $settings['multiStepSummaryButtonText'] ? $settings['multiStepSummaryButtonText'] : 'Show Summary', 'bricks'); ?>
                </button>
            <?php } ?>

            <?php

            // hCaptcha
            if (isset($settings['enableHCaptcha']) && $settings['enableHCaptcha'] === true && $this->check_for_hcaptcha_keys()[0] === true) {
                $hCaptcha_theme = isset($settings['hCaptchaTheme']) && $settings['hCaptchaTheme'] === 'light' ? 'light' : 'dark';
                echo '<div class="brf-hcaptcha-wrapper form-group" '
                    . (isset($settings['hCaptchaInfoMessage']) && !empty($settings['hCaptchaInfoMessage'])
                        ? "data-info='" . $settings['hCaptchaInfoMessage'] . "'"
                        : '')
                    . '><div data-theme="' . $hCaptcha_theme . '" class="h-captcha" data-sitekey="' . $this->check_for_hcaptcha_keys()[1] . '"></div></div>';
            }

            ?>

            <?php if ($this->has_children() == false) { ?>

                <div <?php echo $this->render_attributes('submit-wrapper'); ?>>

                    <button type="submit" <?php echo $this->render_attributes('submit-button'); ?>>
                        <?php
                        if ($submit_button_icon_position === 'left' && $submit_button_icon) {
                            echo $submit_button_icon;
                        }

                        if (!isset($settings['submitButtonIcon']) || (isset($settings['submitButtonIcon']) && isset($settings['submitButtonText']))) {
                            $this->set_attribute('submitButtonText', 'class', 'text');

                            $submit_button_text = isset($settings['submitButtonText']) ? esc_html($settings['submitButtonText']) : esc_html__('Send', 'bricks');

                            echo "<span {$this->render_attributes('submitButtonText')}>$submit_button_text</span>";
                        }

                        echo '<span class="loading">' . $loading_svg . '</span>';

                        if ($submit_button_icon_position === 'right' && $submit_button_icon) {
                            echo $submit_button_icon;
                        }
                        ?>
                    </button>
                </div>

            <?php } ?>

            <?php if (in_array('step', array_column($settings['fields'], 'type')) && $this->has_children() == false) { ?>
                <div class="form-group step-progress">
                    <button type="button" class="bricks-button next">
                        <?php esc_html_e(isset($settings['multiStepNextText']) && $settings['multiStepNextText'] ? $settings['multiStepNextText'] : 'Next', 'bricks'); ?>
                    </button>
                    <button type="button" class="bricks-button prev">
                        <?php esc_html_e(isset($settings['multiStepPreviousText']) && $settings['multiStepPreviousText'] ? $settings['multiStepPreviousText'] : 'Previous', 'bricks'); ?>
                    </button>
                </div>
            <?php } ?>


            <?php if (bricks_is_builder() || bricks_is_rest_call()) { ?>

                <?php if (isset($settings['multiStepSummary']) && $settings['multiStepSummary']) { ?>
                    <div id="brf-summary" style="margin-top: 50px;">
                        <h3 class="brf-summary-headline">Summary Preview</h3>
                        <div class="brf-summary-item">
                            <h4>Headline One</h4>
                            <p>Form Value One</p>
                        </div>
                        <div class="brf-summary-item">
                            <h4>Headline Two</h4>
                            <p>Form Value Two</p>
                        </div>
                    </div>
                <?php } ?>

                <?php if (isset($settings['multiStepShowSteps']) && $settings['multiStepShowSteps']) { ?>
                    <h3 class="brf-summary-preview" style="width: 100%; margin-top: 25px">Step Preview</h3>
                    <div class="brf-steps">
                        <span class="brf-step current">Step 1</span>
                        <span class="brf-step">Step 2</span>
                        <span class="brf-step">Step 3</span>
                    </div>
                <?php } ?>


            <?php } ?>

            <?php $this->render_recaptcha(); ?>

        </form>

<?php
    }

    /**
     * Render recaptcha attributes and error message
     *
     * @since 1.5
     */
    public function render_recaptcha()
    {

        $settings = $this->settings;

        if (!isset($settings['enableRecaptcha'])) {
            return;
        }

        $recaptcha_key = !empty(Database::$global_settings['apiKeyGoogleRecaptcha']) ? Database::$global_settings['apiKeyGoogleRecaptcha'] : false;

        if (!$recaptcha_key) {
            return;
        }

        $this->set_attribute('recaptcha', 'id', 'recaptcha-' . esc_attr($this->id));
        $this->set_attribute('recaptcha', 'data-key', $recaptcha_key);
        $this->set_attribute('recaptcha', 'class', 'recaptcha-hidden');

        echo '<div class="form-group recaptcha-error">';
        echo '<div class="brxe-alert danger">';
        echo '<p>' . esc_html__('Google reCaptcha: Invalid site key.', 'bricks') . '</p>';
        echo '</div>';
        echo '</div>';

        echo "<div {$this->render_attributes('recaptcha')}></div>";
    }
}
