<?php

namespace Bricksforge\ProForms;

use Bricksforge\Helper\ElementsHelper as ElementsHelper;

if (!defined('ABSPATH')) {
    exit;
}

class Helper
{

    static function get_autocomplete_options()
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

    static function get_submit_conditions()
    {
        return [
            'option'                   => esc_html__('Database: Option', 'bricksforge'),
            'post_meta'                => esc_html__('Post Meta Field', 'bricksforge'),
            'storage_item'             => esc_html__('Storage Item', 'bricksforge'),
            'form_field'               => esc_html__('Form Field', 'bricksforge'),
            'submission_count_reached' => esc_html__('Submission Limit Reached', 'bricksforge'),
            'submission_field'         => esc_html__('Submission Field (ID)', 'bricksforge'),
        ];
    }

    static function get_field_conditions()
    {
        return [
            'form_field'               => esc_html__('Form Field', 'bricksforge'),
            'storage_item'             => esc_html__('Storage Item', 'bricksforge'),
        ];
    }

    static function get_condition_operators()
    {
        return [
            '=='           => esc_html__('Is Equal', 'bricksforge'),
            '!='           => esc_html__('Is Not Equal', 'bricksforge'),
            '>'            => esc_html__('Is Greater Than', 'bricksforge'),
            '>='           => esc_html__('Is Greater Than or Equal', 'bricksforge'),
            '<'            => esc_html__('Is Less Than', 'bricksforge'),
            '<='           => esc_html__('Is Less Than or Equal', 'bricksforge'),
            'contains'     => esc_html__('Contains', 'bricksforge'),
            'not_contains' => esc_html__('Not Contains', 'bricksforge'),
            'starts_with'  => esc_html__('Starts With', 'bricksforge'),
            'ends_with'    => esc_html__('Ends With', 'bricksforge'),
            'empty'        => esc_html__('Is Empty', 'bricksforge'),
            'not_empty'    => esc_html__('Is Not Empty', 'bricksforge'),
            'exists'       => esc_html__('Exists', 'bricksforge'),
            'not_exists'   => esc_html__('Not Exists', 'bricksforge'),
        ];
    }

    static function get_condition_data_types()
    {
        return [
            'string' => esc_html__('String', 'bricksforge'),
            'number' => esc_html__('Number', 'bricksforge'),
            'array'  => esc_html__('Array', 'bricksforge'),
        ];
    }

    static function get_loop_controls()
    {
        $controls = [];

        $controls['hasLoop'] = [
            'label' => esc_html__('Use query loop', 'bricksforge'),
            'type'  => 'checkbox',
        ];

        $controls['query'] = [
            'label'    => esc_html__('Query', 'bricksforge'),
            'type'     => 'query',
            'popup'    => true,
            'inline'   => true,
            'required' => [
                'hasLoop',
                '=',
                true,
            ],
        ];

        return $controls;
    }

    static function get_default_controls($field_type = '')
    {
        $controls = [];

        $default_width = '100%';
        $default_width_selector = 'input';

        if ($field_type == 'checkbox' || $field_type == 'radio') {
            $default_width = 'auto';
            $default_width_selector = '&';
        }

        if ($field_type == 'checkbox_wrapper' || $field_type == 'radio_wrapper') {
            $default_width_selector = '&';
        }

        $needs_width = !in_array($field_type, ['checkbox', 'radio', 'image-checkbox', 'image-radio']);
        $needs_initial_value = !in_array($field_type, ['checkbox_wrapper', 'radio_wrapper']);
        $needs_required = !in_array($field_type, ['checkbox_wrapper', 'radio_wrapper', 'hidden']);
        $needs_icon = !in_array($field_type, ['file', 'checkbox_wrapper', 'radio_wrapper', 'hidden', 'checkbox', 'radio', 'card-checkbox', 'card-radio']);
        $needs_custom_id = !in_array($field_type, ['checkbox', 'radio', 'card-checkbox', 'card-radio', 'image-checkbox', 'image-radio']);
        $needs_pattern = in_array($field_type, ['text', 'email', 'number', 'tel', 'url', 'password', 'textarea']);

        $initial_value_default = '';

        // If types like checkboxes or radios or options, set the initial value to "value"
        if (in_array($field_type, ['checkbox', 'radio', 'card-checkbox', 'card-radio', 'image-checkbox', 'image-radio'])) {
            $initial_value_default = 'Value';
        }

        // ID
        $id_description = esc_html__('The ID is used to identify the field in the form submission. If not set, the element ID will be used.', 'bricksforge');

        if ($needs_custom_id) {
            $controls['id'] = [
                'group' => 'general',
                'label'          => esc_html__('Custom ID', 'bricksforge'),
                'description'    => $id_description,
                'type'           => 'text',
                'inline'         => true,
                'spellcheck'     => false,
                'hasDynamicData' => false,
                'default' => \Bricks\Helpers::generate_random_id(false)
            ];
        }

        // Pattern
        if ($needs_pattern) {
            $controls['pattern'] = [
                'group' => 'general',
                'label'          => esc_html__('Pattern', 'bricksforge'),
                'description'    => esc_html__('Expects a regular expression. (For example: [56]*)', 'bricksforge'),
                'type'           => 'text',
                'inline'         => true,
                'spellcheck'     => false,
                'hasDynamicData' => false,
            ];
        }

        // Label
        if ($field_type != "hidden") {
            $controls['label'] = [
                'group' => 'general',
                'label'          => esc_html__('Label', 'bricksforge'),
                'type'           => 'text',
                'inline'         => true,
                'spellcheck'     => false,
                'hasDynamicData' => true,
                'default'        => esc_html__('Label', 'bricksforge'),
            ];
        }


        // Initial Value
        if ($needs_initial_value) {
            $controls['value'] = [
                'group' => 'general',
                'label'          => esc_html__('Value', 'bricksforge'),
                'type'           => 'text',
                'inline'         => true,
                'spellcheck'     => false,
                'hasDynamicData' => true,
                'default'        => $initial_value_default,
            ];
        }

        // Width
        if ($needs_width) {
            $controls['width'] = [
                'group' => 'general',
                'label'          => esc_html__('Width', 'bricksforge'),
                'type'           => 'text',
                'inline'         => true,
                'spellcheck'     => false,
                'hasDynamicData' => true,
                'default'        => $default_width,
                'rerender' => true,
                'css' => [
                    [
                        'property' => 'width',
                        'selector' => $default_width_selector
                    ],
                ],
            ];
        }

        if ($field_type == 'textarea') {

            // Height
            $controls['height'] = [
                'group'    => 'general',
                'label'    => esc_html__('Height', 'bricksforge'),
                'type'     => 'number',
                'units'    => true,
                'css'      => [
                    [
                        'property' => 'height',
                    ],
                ],
            ];
        }

        // Required
        if ($needs_required) {
            $controls['required'] = [
                'group' => 'general',
                'label'          => esc_html__('Required', 'bricksforge'),
                'type'           => 'checkbox',
                'default'        => false,
                'description'    => esc_html__('If checked, the field will be required.', 'bricksforge'),
            ];
        }

        if ($needs_icon) {
            $controls['icon'] = [
                'group' => 'general',
                'label' => esc_html__('Icon', 'bricksforge'),
                'type'  => 'icon',
            ];
        }

        return $controls;
    }

    static function get_accessibility_controls()
    {
        $controls = [];

        // Outline (Accessibility)
        $controls['outline'] = [
            'group' => 'accessibility',
            'label'          => esc_html__('Focus Outline', 'bricksforge'),
            'type'           => 'text',
            'css' => [
                [
                    'property' => 'outline',
                    'selector' => 'input:focus-visible + label',
                ],
            ],
        ];

        // Border
        $controls['border'] = [
            'group' => 'accessibility',
            'label'          => esc_html__('Focus Border', 'bricksforge'),
            'type'           => 'border',
            'css' => [
                [
                    'property' => 'border',
                    'selector' => 'input:focus-visible + label',
                ],
            ],
        ];

        // Filter (Accessibility)
        $controls['filter'] = [
            'group' => 'accessibility',
            'label'          => esc_html__('Focus Filter', 'bricksforge'),
            'type'           => 'filters',
            'inline' => true,
            'css' => [
                [
                    'property' => 'filter',
                    'selector' => 'input:focus-visible + label',
                ],
            ],
        ];

        // Transform (Accessibility)
        $controls['transform'] = [
            'group' => 'accessibility',
            'label'          => esc_html__('Focus Transform', 'bricksforge'),
            'type'           => 'transform',
            'css' => [
                [
                    'property' => 'transform',
                    'selector' => 'input:focus-visible + label',
                ],
            ],
        ];

        return $controls;
    }

    static function get_condition_controls()
    {
        $controls = [];


        $controls['hasConditions'] = [
            'group' => 'conditions',
            'label' => esc_html__('Add Conditions', 'bricksforge'),
            'type'  => 'checkbox',
            'default' => false,
        ];

        $controls['conditions'] = [
            'group' => 'conditions',
            'label' => esc_html__('Conditions', 'bricksforge'),
            'type'  => 'repeater',
            'titleProperty' => 'condition',
            'required' => [['hasConditions', '=', true]],
            'fields'        => [
                'postId'   => [
                    'label'       => esc_html__('Post ID', 'bricksforge'),
                    'type'        => 'text',
                    'placeholder' => 'Leave Empty For Current Post ID',
                    'required'    => [['condition', '=', 'post_meta']],
                ],
                'condition'         => [
                    'tab'     => 'content',
                    'group'   => 'submitButton',
                    'type'    => 'select',
                    'options' => self::get_field_conditions(),
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
                    'options'  => self::get_condition_operators(),
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
                    'label'    => esc_html__('Data Type', 'bricksforge'),
                    'type'     => 'select',
                    'options'  => self::get_condition_data_types(),
                    'default'  => 'string'
                ]
            ]
        ];

        $controls['conditionsRelation'] = [
            'group' => 'conditions',
            'label' => esc_html__('Conditions Relation', 'bricksforge'),
            'type'  => 'select',
            'required' => [['hasConditions', '=', true]],
            'options' => [
                'and' => esc_html__('AND', 'bricksforge'),
                'or'  => esc_html__('OR', 'bricksforge'),
            ],
            'default' => 'and'
        ];

        return $controls;
    }

    static function get_data_source_controls()
    {
        $controls = [];

        $controls['dataSourceCustom'] = [
            'group' => 'general',
            'label' => esc_html__('Data', 'bricksforge'),
            'type'  => 'repeater',
            'titleProperty' => 'label',
            'fields' => [
                'value' => [
                    'label' => esc_html__('Value', 'bricksforge'),
                    'type'  => 'text',
                ],
                'label' => [
                    'label' => esc_html__('Label', 'bricksforge'),
                    'type'  => 'text',
                ]
            ],
        ];

        return $controls;
    }

    static function get_button_style_controls()
    {
        $controls = [];

        // Width
        $controls['width'] = [
            'group' => 'style',
            'label'          => esc_html__('Width', 'bricksforge'),
            'type'           => 'number',
            'units' => true,
            'css' => [
                [
                    'property' => 'width',
                ],
                [
                    'property' => 'width',
                    'selector' => 'button',
                    'value' => '100%'
                ],
            ],
        ];

        // Background
        $controls['background'] = [
            'group' => 'style',
            'label'          => esc_html__('Background', 'bricksforge'),
            'type'           => 'background',
            'css' => [
                [
                    'property' => 'background',
                    'selector' => 'button'
                ],
            ],
        ];

        // Typography
        $controls['typography'] = [
            'group' => 'style',
            'label'          => esc_html__('Typography', 'bricksforge'),
            'type'           => 'typography',
            'css' => [
                [
                    'property' => 'typography',
                    'selector' => 'button'
                ],
            ],
        ];

        // Padding
        $controls['padding'] = [
            'group' => 'style',
            'label'          => esc_html__('Padding', 'bricksforge'),
            'type'           => 'spacing',
            'css' => [
                [
                    'property' => 'padding',
                    'selector' => 'button'
                ],
            ],
        ];

        // Border
        $controls['border'] = [
            'group' => 'style',
            'label'          => esc_html__('Border', 'bricksforge'),
            'type'           => 'border',
            'css' => [
                [
                    'property' => 'border',
                    'selector' => 'button'
                ],
            ],
        ];

        // Transform
        $controls['transform'] = [
            'group' => 'style',
            'label'          => esc_html__('Transform', 'bricksforge'),
            'type'           => 'transform',
            'css' => [
                [
                    'property' => 'transform',
                    'selector' => 'button'
                ],
            ],
        ];

        return $controls;
    }

    static function get_advanced_controls()
    {
        $controls = [];

        // Custom CSS Class
        $controls['cssClass'] = [
            'group' => 'general',
            'label' => esc_html__('CSS Class', 'bricksforge'),
            'type'  => 'text',
            'inline'         => true,
        ];

        return $controls;
    }

    static function get_nestable_parent_settings($element,  $depth = 0)
    {
        if ($depth > 10) { // Maximum recursion depth
            return false;
        }

        $parent_id = !empty($element['parent']) ? $element['parent'] : false;

        if (bricks_is_builder_call()) {
            // $elements selbst befüllen mit den children
        }

        if (isset($parent_id)) {
            $parent_element = !empty(\Bricks\Frontend::$elements[$parent_id]) ? \Bricks\Frontend::$elements[$parent_id] : false;

            if (!$parent_element) {
                foreach (ElementsHelper::$page_data as $element) {
                    if ($element['id'] == $parent_id) {
                        $parent_element = $element;
                        break;
                    }
                }
            }

            if (!$parent_element && bricks_is_builder_call()) {
                $post_id = get_the_ID();

                $parent_element = \Bricks\Helpers::get_element_data($post_id, $parent_id);

                if (isset($parent_element) && isset($parent_element['element'])) {
                    $parent_element = $parent_element['element'];
                }
            }

            // If there is no parent element, we stop here
            if (!isset($parent_element) || !$parent_element) {
                return false;
            }

            if ($parent_element['name'] === 'brf-pro-forms') {
                return $parent_element['settings'];
            } else {
                // Return the result of the recursive call
                return self::get_nestable_parent_settings($parent_element, $depth + 1);
            }
        }

        return false;
    }

    static function get_parent($name = "brf-pro-forms-field-checkbox-wrapper", $element = [], $depth = 0)
    {
        if ($depth > 10) { // Maximum recursion depth
            return false;
        }

        $parent_id = !empty($element['parent']) ? $element['parent'] : false;

        if (isset($parent_id)) {
            $parent_element = !empty(\Bricks\Frontend::$elements[$parent_id]) ? \Bricks\Frontend::$elements[$parent_id] : false;

            // If there is no parent element, we stop here
            if (!isset($parent_element) || !$parent_element) {
                return false;
            }

            if ($parent_element['name'] === $name) {
                return $parent_element;
            } else {
                // Return the result of the recursive call
                return self::get_nestable_parent_settings($parent_element, $depth + 1);
            }
        }

        return false;
    }

    static function parse_options($settings)
    {
        $options = [];

        // Custom
        if (isset($settings['dataSourceCustom']) && $settings['dataSourceCustom']) {
            foreach ($settings['dataSourceCustom'] as $option) {
                $options[] = [
                    'value' => $option['value'],
                    'label' => $option['label'],
                ];
            }
        }

        return $options;
    }

    static function get_quill_formats()
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

    static function get_color_palettes()
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
}
