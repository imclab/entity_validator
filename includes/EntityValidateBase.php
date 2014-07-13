<?php

/**
 * Abstract entity validation.
 */
abstract class EntityValidateBase implements EntityValidateInterface {

  /**
   * The entity type.
   *
   * @var string
   */
  protected $entityType;

  /**
   * The bundle of the node.
   *
   * @var String.
   */
  protected $bundle;

  /**
   * List of fields keyed by machine name and valued with the field's value.
   *
   * @var Array.
   */
  protected $fields = array();

  /**
   * Store the errors in case the error set to 0.
   *
   * @var Array
   */
  protected $errors = array();

  /**
   * Constructs a EntityValidateBase object.
   *
   * @param array $plugin
   *   Plugin definition.
   */
  public function __construct($plugin) {
    $this->plugin = $plugin;
    $this->entityType = $plugin['entity_type'];
    $this->bundle = $plugin['bundle'];
  }

  /**
   * {@inheritdoc}
   */
  public function setBundle($bundle) {
    $this->bundle = $bundle;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getBundle() {
    return $this->bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityType($entity_type) {
    $this->entityType = $entity_type;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType() {
    return $this->entityType;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldsInfo() {
    $fields = array();
    $entity_info = entity_get_info($this->entityType);
    $keys = $entity_info['entity keys'];

    // When the entity has a label key we need to verify it's not empty.
    if (!empty($keys['label'])) {
      $fields[$keys['label']] = array(
        'validators' => array(
          'isNotEmpty',
        ),
      );
    }

    $instances_info = field_info_instances($this->getEntityType(), $this->getBundle());

    foreach ($instances_info as $instance_info) {

      if ($instance_info['required']) {
        // Loading default value of the fields and the instance.
        $fields[$instance_info['field_name']]['validators'][] = 'isNotEmpty';
      }

      $field_info = field_info_field($instance_info['field_name']);

      if ($field_info['type'] == 'image') {
        $fields[$instance_info['field_name']]['validators'][] = 'validateImageField';
      }
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function validate($entity, $silent = FALSE) {
    // Clear any previous error messages.
    $this->clearErrors();
    if (!$fields_info = $this->getFieldsInfo()) {
      return TRUE;
    }

    $wrapper = entity_metadata_wrapper($this->entityType, $entity);

    // Collect the fields callbacks.
    foreach ($fields_info as $field_name => $info) {
      $property = isset($info['property']) ? $info['property'] : $field_name;

      if (!empty($info['preprocess'])) {
        $this->invokeMethods($wrapper->{$property}, $info['preprocess'], TRUE);
      }

      // Loading default value of the fields and the instance.
      $field_info = field_info_field($field_name);
      $field_type_info = field_info_field_types($field_info['type']);

      if (isset($field_type_info['property_type']) && $wrapper->{$property}->value()) {
        $this->isValidValue($field_name, $wrapper->{$property}->value(), $field_type_info['property_type']);
      }

      if (!empty($info['validators'])) {
        $this->invokeMethods($wrapper->{$property}, $info['validators']);
      }
    }

    if (!$errors = $this->getErrors()) {
      return TRUE;
    }

    if ($silent) {
      // Don't throw an error, and just indicate validation failed.
      return FALSE;
    }

    $params = array('@errors' => $errors);
    throw new \EntityValidatorException(format_string('The validation process failed: @errors', $params));
  }

  /**
   * Preprocess the field. This is useful when we need to alter a field before
   * the validation process.
   *
   * @param \EntityMetadataWrapper $property_wrapper
   *  The property wrapper.
   * @param array $methods
   *  Array of methods.
   * @param bool $assign_value
   *  Determine if we need to assign the from the callback to the field. Default
   *  to FALSE.
   */
  protected function invokeMethods(EntityMetadataWrapper $property_wrapper, array $methods, $assign_value = FALSE) {
    foreach ($methods as $method) {
      $value = $property_wrapper->value();

      $info = $property_wrapper->info();
      $new_value = $this->{$method}($info['name'], $value);
      if ($assign_value && $new_value != $value) {
        // Setting the fields value with the wrapper.
        $property_wrapper->set($value);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setError($field_name, $message, $params = array()) {
    $params['@field'] = $field_name;
    $this->errors[$field_name][] = array('message' => $message, 'params' => $params);
  }

  /**
   * {@inheritdoc}
   */
  public function getErrors($squash = TRUE) {
    if (!$squash) {
      return $this->errors;
    }

    $return = array();
    foreach ($this->errors as $errors) {
      foreach ($errors as $error) {
        $error += array('params' => array());
        $return[] = format_string($error['message'], $error['params']);
      }
    }

    return implode("\n\r", $return);
  }

  /**
   * {@inheritdoc}
   */
  public function clearErrors() {
    $this->errors = array();
  }

  /**
   * Verify the field is not empty.
   *
   * @param $field_name
   *  The field name.
   * @param $value
   *  The value of the field.
   *
   * @return boolean
   */
  public function isNotEmpty($field_name, $value) {
    if (empty($value)) {
      $params = array(
        '@field' => $field_name,
      );

      $this->setError($field_name, 'The field @field cannot be empty.', $params);
    }
  }

  /**
   * Special validate callback: usually all the validator have two arguments,
   * value and field. This validate method check the value of the field using
   * the entity API module.
   *
   * @param $field_name
   *  The field name.
   * @param $value
   *  The value of the field.
   * @param $type
   *  The type of the field.
   *
   * @return boolean
   */
  public function isValidValue($field_name, $value, $type) {
    if (!entity_property_verify_data_type($value, $type)) {
      $params = array(
        '@value' => (String) $value,
        '@field' => $field_name,
      );

      $this->setError($field_name, 'The value @value is invalid for the field @field.', $params);
    }
  }

  /**
   * Validate the field image: Check the image is the correct size.
   */
  public function validateImageField($field_name, $value) {
    $info = field_info_instance($this->getEntityType(), $field_name, $this->getBundle());
    $settings = $info['settings'];

    $params = array(
      '@width' => $value['width'],
      '@height' => $value['height'],
    );

    if (!empty($settings['max_resolution'])) {
      list($max_height, $max_width) = explode("x", $settings['max_resolution']);

      $params += array(
        '@max-width' => $max_width,
        '@max-height' => $max_height,
      );

      if ($value['width'] > $max_width) {
        $this->setError($field_name, 'The width of the image(@width) is bigger then the allowed size(@max-width)', $params);
      }

      if ($value['height'] > $max_height) {
        $this->setError($field_name, 'The width of the image(@height) is bigger then the allowed size(@max-height)', $params);
      }
    }

    if (!empty($settings['min_resolution'])) {
      list($min_height, $min_width) = explode("x", $settings['min_resolution']);

      $params += array(
        '@min-width' => $min_width,
        '@min-height' => $min_height,
      );

      if ($value['width'] < $min_width) {
        $this->setError($field_name, 'The width of the image(@width) is bigger then the allowed size(@min-width) ', $params);
      }

      if ($value['height'] < $min_height) {
        $this->setError($field_name, 'The width of the image(@height) is bigger then the allowed size(@min-height) ', $params);
      }
    }
  }
}
