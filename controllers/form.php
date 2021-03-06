<?php
class midgardmvc_ui_forms_controllers_form extends midgardmvc_core_controllers_baseclasses_crud
{
    private $component_name = 'midgardmvc_ui_forms';

    public function __construct(midgardmvc_core_request $request)
    {
        parent::__construct($request);

        $this->mvc = midgardmvc_core::get_instance();

        $this->mvc->i18n->set_translation_domain($this->component_name);

        $default_language = $this->mvc->configuration->default_language;

        if (! isset($default_language))
        {
            $default_language = 'en_US';
        }

        $this->mvc->i18n->set_language($default_language, false);
    }

    private function load_parent(array $args)
    {
        try
        {
            $this->parent = midgard_object_class::get_object_by_guid($args['parent']);
        }
        catch (midgard_error_exception $e)
        {
            throw new midgardmvc_exception_notfound("Object not found: " . $e->getMessage());
        }
    }

    public function load_object(array $args)
    {
        midgardmvc_core::get_instance()->authorization->require_user();

        try
        {
            $this->object = new midgardmvc_ui_forms_form($args['form']);
        }
        catch (midgard_error_exception $e)
        {
            throw new midgardmvc_exception_notfound("Form not found: " . $e->getMessage());
        }
    }

    public function prepare_new_object(array $args)
    {
        midgardmvc_core::get_instance()->authorization->require_user();
        $this->load_parent($args);
        $this->object = new midgardmvc_ui_forms_form();
        $this->object->parent = $this->parent->guid;
    }

    public function load_form()
    {
        $this->form = midgardmvc_helper_forms::create('midgardmvc_ui_forms_form');

        $field = $this->form->add_field('title', 'text');
        $field->set_value($this->object->title);
        $widget = $field->set_widget('text');
        $widget->set_label($this->mvc->i18n->get('title_form_title', $this->component_name));

        $now = new midgard_datetime();
        if ($this->object->metadata->schedulestart->__toString() == "0001-01-01T00:00:00+00:00")
        {
            $this->object->metadata->schedulestart = $now;
        }
        if ($this->object->metadata->scheduleend->__toString() == "0001-01-01T00:00:00+00:00")
        {
            $interval = new DateInterval('P1Y');
            $now->add($interval);
            $this->object->metadata->scheduleend = $now;
        }

        unset($now);

        $field = $this->form->add_field('metadata.schedulestart', 'datetime');
        $field->set_value($this->object->metadata->schedulestart);
        $widget = $field->set_widget('datetime');
        $widget->set_id('form_schedule_start');
        $widget->set_label($this->mvc->i18n->get('title_schedule_start', $this->component_name));

        $field = $this->form->add_field('metadata.scheduleend', 'datetime');
        $field->set_value($this->object->metadata->scheduleend);
        $widget = $field->set_widget('datetime');
        $widget->set_id('form_schedule_end');
        $widget->set_label($this->mvc->i18n->get('title_schedule_end', $this->component_name));

        $this->data['admin'] = false;

        if ($this->mvc->authentication->is_user())
        {
            if ($this->mvc->authentication->get_user()->is_admin())
            {
                $this->data['admin'] = true;
                $this->data['form_preview'] = midgardmvc_ui_forms_generator::get_by_form($this->object, true);
                $this->data['form_preview']->set_readonly(true);

                $this->data['field_create_url'] = midgardmvc_core::get_instance()->dispatcher->generate_url
                (
                    'field_create', array
                    (
                        'form' => $this->object->guid,
                    ),
                    $this->request
                );
            }
        }
    }

    public function get_read(array $args)
    {
        parent::get_read($args);

        // Load a readonly preview of the form
        $this->data['form_preview'] = midgardmvc_ui_forms_generator::get_by_form($this->object, true);
        $this->data['form_preview']->set_readonly(true);

        if ($this->mvc->authentication->is_user())
        {
            if ($this->mvc->authentication->get_user()->is_admin())
            {
                $this->data['admin'] = true;

                self::load_form($args);
                $this->data['form'] = $this->form;

                $this->data['field_create_url'] = midgardmvc_core::get_instance()->dispatcher->generate_url
                (
                    'field_create', array
                    (
                        'form' => $this->object->guid,
                    ),
                    $this->request
                );
            }
        }
    }

    /**
     * Admins may use the show template to post changes
     */
    public function post_read(array $args)
    {
        midgardmvc_core::get_instance()->authorization->require_admin();

        $this->get_update($args);

        try
        {
            $transaction = new midgard_transaction();
            $transaction->begin();
            parent::process_form();
            $this->object->metadata->schedulestart->modify(new midgard_datetime($_POST['metadata_schedulestart']));
            $this->object->metadata->scheduleend->modify(new midgard_datetime($_POST['metadata_scheduleend']));
            $this->object->update();
            $transaction->commit();

            // FIXME: We can remove this once signals work again
            midgardmvc_core::get_instance()->cache->invalidate(array($this->object->guid));

            $this->relocate_to_read();
        }
        catch (midgardmvc_helper_forms_exception_validation $e)
        {
            // TODO: UImessage
        }
    }

    public function get_url_read()
    {
        return midgardmvc_core::get_instance()->dispatcher->generate_url
        (
            'form_read', array
            (
                'form' => $this->object->guid
            ),
            $this->request
        );
    }

    public function get_url_update()
    {
        return midgardmvc_core::get_instance()->dispatcher->generate_url
        (
            'form_update', array
            (
                'form' => $this->object->guid
            ),
            $this->request
        );
    }

    /**
     * Prepares stuff for creation
     */
    public function get_create(array $args)
    {
        midgardmvc_core::get_instance()->authorization->require_admin();
        parent::get_create($args);
        $this->data['title'] = $this->mvc->i18n->get('title_create_form', $this->component_name);
    }

    /**
     * Prepares stuff for creation
     */
    public function get_update(array $args)
    {
        midgardmvc_core::get_instance()->authorization->require_admin();
        parent::get_update($args);
        $this->data['title'] = $this->mvc->i18n->get('title_update_form', $this->component_name);
    }
}
