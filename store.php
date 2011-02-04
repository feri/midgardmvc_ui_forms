<?php
class midgardmvc_ui_forms_store
{
    public static function store_form(midgardmvc_helper_forms_group $form, midgardmvc_ui_forms_form_instance $instance)
    {
        $transaction = new midgard_transaction();
        $transaction->begin();

        // Go through form items and fill the object
        $items = $form->items;
        foreach ($items as $key => $item)
        {
            if ($item instanceof midgardmvc_helper_forms_group)
            {
                // TODO: Add support for subforms
                continue;
            }
            $field_instance = self::get_instance_for_field($item, $instance);
            if (is_null($field_instance))
            {
                continue;
            }

            if (!self::store_field($item, $field_instance))
            {
                $transaction->rollback();
                return false;
            }
        }

        $transaction->commit();
        return true;
    }

    public static function get_instance_class_for_field(midgardmvc_helper_forms_field $field)
    {
        switch (get_class($field))
        {
            case 'midgardmvc_helper_forms_field_text':
                return 'midgardmvc_ui_forms_form_instance_field_string';
            case 'midgardmvc_helper_forms_field_boolean':
                return 'midgardmvc_ui_forms_form_instance_field_boolean';
        }
        return null;
    }

    public static function get_instance_for_field(midgardmvc_helper_forms_field $field, midgardmvc_ui_forms_form_instance $instance)
    {
        $instance_class = self::get_instance_class_for_field($field);
        if (is_null($instance_class))
        {
            return null;
        }

        // Fetch fields from the database
        $storage = new midgard_query_storage($instance_class);
        $q = new midgard_query_select($storage);
        $q->set_constraint
        (
            new midgard_query_constraint
            (
                new midgard_query_property('form', $storage),
                '=',
                new midgard_query_value($instance->id)
            )
        );
        $q->set_constraint
        (
            new midgard_query_constraint
            (
                new midgard_query_property('field', $storage),
                '=',
                new midgard_query_value($field->get_name())
            )
        );
        $q->execute();
        $list_of_field_instances = $q->list_objects();
        if (empty($list_of_field_instances))
        {
            $field_instance = new $instance_class();
            $field_instance->form = $instance->id;
            $field_instance->field = $field->get_name();
            return $field_instance;
        }

        return $list_of_field_instances[0];
    }

    public static function store_field(midgardmvc_helper_forms_field $field, $field_instance)
    {
        if ($field->get_value() == $field_instance->value)
        {
            return true;
        }

        $field_instance->value = $field->get_value();

        if (!$field_instance->guid)
        {
            return $field_instance->create();
        }
        return $field_instance->update();
    }
}