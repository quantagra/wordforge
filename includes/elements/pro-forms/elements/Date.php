<?php

namespace Bricks;

use \Bricksforge\ProForms\Helper as Helper;

if (!defined('ABSPATH'))
    exit;

class Brf_Pro_Forms_Date extends \Bricks\Element
{

    public $category = 'bricksforge forms';
    public $name = 'brf-pro-forms-field-date';
    public $icon = 'fa-solid fa-calendar';
    public $css_selector = '';
    public $scripts = [];
    public $nestable = false;

    public function get_label()
    {
        return esc_html__("Date", 'bricksforge');
    }

    public function enqueue_scripts()
    {
        wp_enqueue_script('bricksforge-elements');

        if (!bricks_is_builder()) {
            wp_enqueue_script('bricks-flatpickr');
            wp_enqueue_style('bricks-flatpickr');
        }

        // Load datepicker localisation (@since 1.8.6)
        $l10n = !empty($this->settings['l10n']) ? $this->settings['l10n'] : '';

        if ($l10n) {
            wp_enqueue_script('bricks-flatpickr-l10n', "https://npmcdn.com/flatpickr@4.6.13/dist/l10n/$l10n.js", ['bricks-flatpickr']);
        }
    }

    public function set_control_groups()
    {
        $this->control_groups['general'] = [
            'title'    => esc_html__('General', 'bricksforge'),
            'tab'      => 'content',
        ];
        $this->control_groups['conditions'] = [
            'title'    => esc_html__('Conditions', 'bricksforge'),
            'tab'      => 'content',
        ];
    }

    public function set_controls()
    {
        $this->controls = array_merge($this->controls, Helper::get_default_controls());

        // Placeholder
        $this->controls['placeholder'] = [
            'group' => 'general',
            'label'          => esc_html__('Placeholder', 'bricksforge'),
            'type'           => 'text',
            'inline'         => true,
            'spellcheck'     => false,
            'hasDynamicData' => true,
        ];

        $this->controls['time'] = [
            'group' => 'general',
            'label'    => esc_html__('Enable time', 'bricks'),
            'type'     => 'checkbox',
        ];

        $this->controls['l10n'] = [
            'group' => 'general',
            'label'       => esc_html__('Language', 'bricks'),
            'type'        => 'text',
            'inline'      => true,
            'description' => sprintf(
                '<a href="https://github.com/flatpickr/flatpickr/tree/master/src/l10n" target="_blank">%s</a> (de, es, fr, etc.)',
                esc_html__('Language codes', 'bricks'),
            ),
        ];

        // Date Format
        $this->controls['dateFormat'] = [
            'group' => 'general',
            'label'       => esc_html__('Date Format', 'bricks'),
            'type'        => 'text',
            'placeholder' => esc_html__('Y-m-d H:i', 'bricks'),
        ];

        $this->controls['minTime'] = [
            'group' => 'general',
            'label'       => esc_html__('Min. time', 'bricks'),
            'type'        => 'text',
            'placeholder' => esc_html__('09:00', 'bricks'),
            'required'    => ['time', '!=', ''],
        ];

        $this->controls['maxTime'] = [
            'group' => 'general',
            'label'       => esc_html__('Max. time', 'bricks'),
            'type'        => 'text',
            'placeholder' => esc_html__('20:00', 'bricks'),
            'required'    => ['time', '!=', ''],
        ];

        // Enable Range
        $this->controls['dateRange'] = [
            'group' => 'general',
            'label'    => esc_html__('Range Picker', 'bricks'),
            'type'     => 'checkbox',
        ];

        // Needs Enable Dates (Checkbox)
        $this->controls['needsEnableDates'] = [
            'group' => 'general',
            'label'    => esc_html__('Enable specific dates', 'bricks'),
            'type'     => 'checkbox',
        ];

        // Enable Dates Source
        $this->controls['enableDatesSource'] = [
            'group' => 'general',
            'label'    => esc_html__('Enable Dates Source', 'bricks'),
            'type'     => 'select',
            'options' => [
                'custom' => esc_html__('Custom', 'bricks'),
                'dynamic' => esc_html__('Dynamic Data', 'bricks'),
            ],
            'default' => 'custom',
            'required' => [['needsEnableDates', '=', true]],
        ];

        // Enable Dates (Repeater)
        $this->controls['enableDates'] = [
            'group' => 'general',
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
            'required' => [['enableDatesSource', '=', 'custom'], ['needsEnableDates', '=', true]],
        ];

        $this->controls['enableDatesDynamic'] = [
            'group' => 'general',
            'label' => esc_html__('Dates To Enable', 'bricks'),
            'type'  => 'text',
            'required' => [['enableDatesSource', '=', 'dynamic'], ['needsEnableDates', '=', true]],
        ];

        // Enable specific weekdays
        $this->controls['needsEnableWeekdays'] = [
            'group' => 'general',
            'label'    => esc_html__('Enable specific weekdays', 'bricks'),
            'type'     => 'checkbox',
        ];

        // (Weekdays) Multi Select
        $this->controls['enableWeekdaysData'] = [
            'group' => 'general',
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
            'required' => [['needsEnableWeekdays', '=', true]],
        ];

        // Needs Disable Dates (Checkbox)
        $this->controls['needsDisableDates'] = [
            'group' => 'general',
            'label'    => esc_html__('Disable specific dates', 'bricks'),
            'type'     => 'checkbox',
            'required' => [['type', '=', ['datepicker']]],
        ];

        // Disable Dates Source
        $this->controls['disableDatesSource'] = [
            'group' => 'general',
            'label'    => esc_html__('Disable Dates Source', 'bricks'),
            'type'     => 'select',
            'options' => [
                'custom' => esc_html__('Custom', 'bricks'),
                'dynamic' => esc_html__('Dynamic Data', 'bricks'),
            ],
            'default' => 'custom',
            'required' => [['needsDisableDates', '=', true]],
        ];

        // Disable Dates (Repeater)
        $this->controls['disableDates'] = [
            'group' => 'general',
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
            'required' => [['disableDatesSource', '=', 'custom'], ['needsDisableDates', '=', true]],
        ];

        // Disable Dates Dynamic
        $this->controls['disableDatesDynamic'] = [
            'group' => 'general',
            'label' => esc_html__('Dates To Disable', 'bricks'),
            'type'  => 'text',
            'required' => [['disableDatesSource', '=', 'dynamic'], ['needsDisableDates', '=', true]],
        ];

        $this->controls['showVisualCalendar'] = [
            'group' => 'general',
            'label' => esc_html__('Show Visual Calendar', 'bricks'),
            'type'  => 'checkbox',
            'default' => false,
            'description' => esc_html__('If checked, the visual calendar will be shown. The input field will be hidden.', 'bricks'),
            'css' => [
                [
                    'property' => 'display',
                    'value' => 'none',
                    'selector' => '.flatpickr.form-control.input',
                ],
            ],

        ];

        $this->controls = array_merge($this->controls, Helper::get_condition_controls());
        $this->controls = array_merge($this->controls, Helper::get_advanced_controls());
    }

    public function render()
    {
        $settings = $this->settings;
        $parent_settings = Helper::get_nestable_parent_settings($this->element) ? Helper::get_nestable_parent_settings($this->element) : [];

        $id = $this->id ? $this->id : false;

        if (isset($settings['id']) && $settings['id']) {
            $id = $settings['id'];
        }

        $random_id = Helpers::generate_random_id(false);
        $label = isset($settings['label']) ? $settings['label'] : false;
        $placeholder = isset($settings['placeholder']) ? $settings['placeholder'] : false;
        $value = isset($settings['value']) ? $settings['value'] : '';
        $required = isset($settings['required']) ? $settings['required'] : false;

        if (!$id && bricks_is_builder()) {
            return $this->render_element_placeholder(
                [
                    'title' => esc_html__('You have to set an ID for your element.', 'bricksforge'),
                ]
            );
        }

        /**
         * Wrapper
         */
        $this->set_attribute('_root', 'class', 'pro-forms-builder-field');
        $this->set_attribute('_root', 'class', 'form-group');
        $this->set_attribute('_root', 'data-element-id', $this->id);

        if ($id !== $this->id) {
            $this->set_attribute('_root', 'data-custom-id', $id);
        }

        // Custom Css Class
        if (isset($settings['cssClass']) && $settings['cssClass']) {
            $this->set_attribute('field', 'class', $settings['cssClass']);
        }

        /**
         * Field
         */
        $this->set_attribute('field', 'type', 'date');
        $this->set_attribute('field', 'id', 'form-field-' . $random_id);
        $this->set_attribute('field', 'name', 'form-field-' . $id);
        $this->set_attribute('field', 'data-label', $label);

        // Class flatpickr
        $this->set_attribute('field', 'class', 'flatpickr');

        if ($placeholder) {
            $this->set_attribute('field', 'placeholder', $placeholder);
        }
        if ($value) {
            $this->set_attribute('field', 'value', $value);
        }
        if ($required) {
            $this->set_attribute('field', 'required', $required);
        }

        $time_24h = get_option('time_format');
        $time_24h = strpos($time_24h, 'H') !== false || strpos($time_24h, 'G') !== false;

        $date_format = isset($settings['time']) ? get_option('date_format') . ' H:i' : get_option('date_format');

        if (isset($settings['dateFormat']) && $settings['dateFormat']) {
            $date_format = $settings['dateFormat'];
        }

        $datepicker_options = [
            // 'allowInput' => true,
            'enableTime' => isset($settings['time']),
            'minTime'    => isset($settings['minTime']) ? $settings['minTime'] : '',
            'maxTime'    => isset($settings['maxTime']) ? $settings['maxTime'] : '',
            'altInput'   => true,
            'altFormat'  => $date_format,
            'dateFormat' => 'Y-m-d H:i',
            'time_24hr'  => $time_24h,
            'mode' => isset($settings['dateRange']) ? 'range' : 'single',
        ];

        // If "Enable Dates" is set, add the dates to the options
        if (isset($settings['needsEnableDates']) && $settings['needsEnableDates'] && isset($settings['enableDatesSource']) && $settings['enableDatesSource'] === 'custom' && isset($settings['enableDates']) && !empty($settings['enableDates'])) {
            $dates = array_map(function ($date) {
                return isset($date['to']) ? ['from' => $date['from'], 'to' => $date['to']] : $date['from'];
            }, $settings['enableDates']);

            $datepicker_options['enable'] = $dates;
        } elseif (isset($settings['needsEnableDates']) && field['needsEnableDates'] && isset($settings['enableDatesSource']) && $settings['enableDatesSource'] === 'dynamic') {
            if (isset($settings['enableDatesDynamic']) && !empty($settings['enableDatesDynamic'])) {
                $datepicker_options['enable'] = bricks_render_dynamic_data($settings['enableDatesDynamic']);
            }
        }

        // If Enable Weekdays is set, add data attribute "enable-weekdays"
        if (isset($settings['needsEnableWeekdays']) && $settings['needsEnableWeekdays'] && isset($settings['enableWeekdaysData']) && !empty($settings['enableWeekdaysData'])) {
            $this->set_attribute("field", 'data-enable-weekdays', $settings['enableWeekdaysData']);
        }

        // If "Disable Dates" is set, add the dates to the options
        if (isset($settings['needsDisableDates']) && $settings['needsDisableDates'] && isset($settings['disableDatesSource']) && $settings['disableDatesSource'] === 'custom' && isset($settings['disableDates']) && !empty($settings['disableDates'])) {
            $dates = array_map(function ($date) {
                return isset($date['to']) ? ['from' => $date['from'], 'to' => $date['to']] : $date['from'];
            }, $settings['disableDates']);

            $datepicker_options['disable'] = $dates;
        } elseif (isset($settings['needsDisableDates']) && field['needsDisableDates'] && isset($settings['disableDatesSource']) && $settings['disableDatesSource'] === 'dynamic') {
            if (isset($settings['disableDatesDynamic']) && !empty($settings['disableDatesDynamic'])) {
                $datepicker_options['disable'] = bricks_render_dynamic_data($settings['disableDatesDynamic']);
            }
        }

        // If showVisualCalendar is set, add the "inline" option
        if (isset($settings['showVisualCalendar']) && $settings['showVisualCalendar']) {
            $datepicker_options['inline'] = true;
        }

        // Localization: https://flatpickr.js.org/localization/ (@since 1.8.6)
        if (!empty($settings['l10n'])) {
            $datepicker_options['locale'] = $settings['l10n'];
        }

        $this->set_attribute("field", 'data-bricks-datepicker-options', wp_json_encode($datepicker_options));

        // Conditions
        if (isset($settings['hasConditions']) && isset($settings['conditions']) && $settings['conditions']) {
            $this->set_attribute('_root', 'data-brf-conditions', json_encode($settings['conditions']));
        }
        if (isset($settings['conditionsRelation']) && $settings['conditionsRelation']) {
            $this->set_attribute('_root', 'data-brf-conditions-relation', $settings['conditionsRelation']);
        }

        // Icons
        if (isset($settings['icon'])) {
            $this->set_attribute("field-icons", 'class', 'input-icon-wrapper');
            $this->set_attribute("field-icons", 'class', isset($parent_settings['iconPosition']) && $parent_settings['iconPosition'] == 'row' ? 'icon-left' : 'icon-right');

            if (isset($parent_settings['iconInset']) && $parent_settings['iconInset'] == true) {
                $this->set_attribute("field-icons", 'class', 'icon-inset');
            }

            if (isset($parent_settings['iconFocusInput']) && $parent_settings['iconFocusInput'] == true) {
                $this->set_attribute("field-icons", 'data-focus', 'true');
            }
        }

        // Required Asterisk
        if (isset($parent_settings['requiredAsterisk']) && $parent_settings['requiredAsterisk'] == true && $required) {
            $this->set_attribute("label", 'class', 'required');
        }

?>
        <div <?php echo $this->render_attributes('_root'); ?>>
            <?php if ($label) : ?>
                <label <?php echo $this->render_attributes('label'); ?> for="form-field-<?php echo $random_id; ?>"><?php echo $label; ?></label>
            <?php endif; ?>
            <?php if (isset($settings['icon'])) { ?>
                <div <?php echo $this->render_attributes("field-icons"); ?>>
                    <span class="input-icon"><?php echo $this->render_icon($settings['icon']) ?></span>
                    <input <?php echo $this->render_attributes('field'); ?>>
                </div>
            <?php } else { ?>
                <input <?php echo $this->render_attributes('field'); ?>>
            <?php } ?>
        </div>
<?php
    }
}
