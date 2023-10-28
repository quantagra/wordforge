<?php

namespace Bricks;

use \Bricksforge\ProForms\Helper as Helper;

if (!defined('ABSPATH'))
    exit;

class Brf_Pro_Forms_Calculation extends \Bricks\Element
{

    public $category = 'bricksforge forms';
    public $name = 'brf-pro-forms-field-calculation';
    public $icon = 'fa-solid fa-calculator';
    public $css_selector = '';
    public $scripts = [];
    public $nestable = false;

    public function get_label()
    {
        return esc_html__("Calculation", 'bricksforge');
    }

    public function enqueue_scripts()
    {
        wp_enqueue_script('bricksforge-elements');
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

        $this->controls['info'] = [
            'group' => 'general',
            'label' => esc_html__('Info', 'bricks'),
            'type'  => 'info',
            'content' => esc_html__('To use form IDs, wrap them in curly braces {}. Example: {mdityr} + 50 / 2', 'bricks'),
        ];

        $this->controls['formula'] = [
            'group' => 'general',
            'label' => esc_html__('Formula', 'bricks'),
            'type'  => 'textarea',
        ];

        $this->controls['roundValue'] = [
            'group' => 'general',
            'label' => esc_html__('Round Value', 'bricks'),
            'type'  => 'checkbox',
            'default' => false,
            'description' => esc_html__('If checked, the value will be rounded to the nearest integer.', 'bricks'),
        ];

        $this->controls['hasCurrencyFormat'] = [
            'group' => 'general',
            'label' => esc_html__('Currency Format', 'bricks'),
            'type'  => 'checkbox',
            'default' => false,
            'description' => esc_html__('If checked, the value will be formatted with two decimal places.', 'bricks'),
        ];

        $this->controls['setEmptyToZero'] = [
            'group' => 'general',
            'label' => esc_html__('Set empty to 0', 'bricks'),
            'type'  => 'checkbox',
            'default' => true,
            'description' => esc_html__('If checked, empty fields will be set to 0.', 'bricks'),
        ];

        $this->controls['emptyMessage'] = [
            'group' => 'general',
            'label' => esc_html__('Empty message', 'bricks'),
            'type'  => 'text',
            'default' => 'Please fill in all fields.',
            'required' => [['setEmptyToZero', '=', false]],
            'description' => esc_html__('The message you want to show if the calculation is invalid because of empty fields ', 'bricks'),
        ];

        $this->controls['onlyRemote'] = [
            'group' => 'general',
            'label' => esc_html__('Only Remote', 'bricks'),
            'type'  => 'checkbox',
            'default' => false,
            'description' => esc_html__('If checked, the calculation input will be hidden. Use Dynamic Data to show the calculation value', 'bricks'),
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
        $this->set_attribute('_root', 'data-field-id', $this->id);

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
        $this->set_attribute('field', 'type', 'text');
        $this->set_attribute('field', 'id', 'form-field-' . $random_id);
        $this->set_attribute('field', 'name', 'form-field-' . $id);

        if ($placeholder) {
            $this->set_attribute('field', 'placeholder', $placeholder);
        }
        if ($value) {
            $this->set_attribute('field', 'value', $value);
        }
        if ($required) {
            $this->set_attribute('field', 'required', $required);
        }

        $this->set_attribute("_root", 'class', 'calculation-field');
        $this->set_attribute("calculation", 'aria-label', isset($settings['label']) && !empty($settings['label']) ? $settings['label'] : '');
        $this->set_attribute("_root", 'data-empty-message', isset($settings['emptyMessage']) ? $settings['emptyMessage'] : '');

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
            <?php if ($label && (!isset($settings['onlyRemote']) || !$settings['onlyRemote'])) : ?>
                <label <?php echo $this->render_attributes('label'); ?> for="form-field-<?php echo $random_id; ?>"><?php echo $label; ?></label>
            <?php endif; ?>
            <?php if (isset($settings['icon'])) { ?>
                <div <?php echo $this->render_attributes("field-icons"); ?>>
                    <span class="input-icon"><?php echo $this->render_icon($settings['icon']) ?></span>
                    <div <?php echo $this->render_attributes("calculation"); ?>>
                        <input readonly type="<?php echo isset($settings['onlyRemote']) && $settings['onlyRemote'] ? 'hidden' : 'text' ?>" class="calculation-result" value="0" <?php echo $this->render_attributes("field"); ?>>
                    </div>
                </div>
            <?php } else { ?>
                <div <?php echo $this->render_attributes("calculation"); ?>>
                    <input readonly type="<?php echo isset($settings['onlyRemote']) && $settings['onlyRemote'] ? 'hidden' : 'text' ?>" class="calculation-result" value="0" <?php echo $this->render_attributes("field"); ?>>
                </div>
            <?php } ?>
        </div>
<?php
    }
}
