<?php

abstract class AbstractValidate implements Validate {

  /**
   * List of fields keyed by machine name and valued with the field's value.
   *
   * @var Array.
   */
  protected $fields;

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
   * The error level.
   *  0 For save the errors for later.
   *  1 for simple drupal_set_message.
   *  2 for throwing exception.
   * @var int
   */
  protected $errorLevel = 1;

  /**
   * Store the errors in case the error set to 0.
   *
   * @var Array
   */
  protected $errors;

  /**
   * Holds metadata about the object.
   *
   * @var Array
   */
  protected $metaData;

  /**
   * @var array
   */
  protected $preValidate = array();

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
  public function setEntity($entity) {
    $this->entityType = $entity;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addField($name, $value) {
    $this->fields[$name] = $value;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFields() {
    return $this->fields;
  }

  /**
   * {@inheritdoc}
   */
  public function setFields($fields) {
    $this->fields = $fields;
    return $this->fields;
  }

  /**
   * {@inheritdoc}
   */
  public function validate() {
    foreach ($this->preValidate as $prevalidate) {
      if (!function_exists($prevalidate)) {
        continue;
      }

      call_user_func_array($prevalidate, array($this));
    }

    foreach ($this->fields as $field => $value) {
      // Loading some default value of the fields and the instance.
      $field_info = field_info_field($field);
      $field_type_info = field_info_field_types($field_info['type']);
      $instance_info = field_info_instance($this->entityType, $field, $this->bundle);

      if ($instance_info['required'] && empty($value)) {
        $this->setError(t('Field %name is empty', array('%name' => $instance_info['label'])));
      }
      else {
        // Use the entity API validation.
        if (isset($field_type_info['property_type']) && !entity_property_verify_data_type($value, $field_type_info['property_type'])) {
          $params = array(
            '%value' => (String) $value,
            '%field-label' => $instance_info['label'],
          );

          $this->setError(t('The value %value is invalid for the field %field-label', $params));
          continue;
        }

        // Node validator validations passed.
        if (isset($field_type_info['node_validator_callback'])) {
          foreach ($field_type_info['node_validator_callback'] as $callback) {
            if (!call_user_func_array($callback, array($this, $value))) {
              $this->setError(t('The given format is not valid: %format', array('%format' => $value)));
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setError($message) {
    if ($this->errorLevel === 0) {
      $this->errors[] = $message;
    }
    else if ($this->errorLevel === 1) {
      drupal_set_message($message, 'error');
    }
    else {
      throw new Exception($message);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setErrorLevel($level) {
    $this->errorLevel = $level;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getErrors() {
    return $this->errors;
  }

  /**
   * {@inheritdoc}
   */
  public function addMetaData($key, $value) {
    $this->metaData[$key] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetaData() {
    return $this->metaData;
  }

  /**
   * {@inheritdoc}
   */
  public function preValidateRegister($function) {
    $this->preValidate[] = $function;
    return $this;
  }
}