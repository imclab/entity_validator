<?php

/**
 * Abstract entity validation.
 */
abstract class AbstractEntityValidate implements EntityValidateInterface {

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
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function validate() {
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

    if (!empty($this->errors)) {
      $params = array(
        '@errors' => implode(", ", $this->errors),
      );
      throw new Exception(t('The validation process failed: @errors', $params));
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function setError($message) {
    $this->errors[] = $message;
  }

  /**
   * Verify if the given value .
   * @param $value
   * @return boolean
   */
  public function isText($value) {
    // TODO: Implement isText() method.
  }

  /**
   * @param $value
   * @return boolean
   */
  public function isNumeric($value) {
    // TODO: Implement isNumeric() method.
  }

  /**
   * @param $value
   * @return boolean
   */
  public function isUnique($value) {
    // TODO: Implement isUnique() method.
  }

  /**
   * @param $value
   * @return boolean
   */
  public function isList($value) {
    // TODO: Implement isList() method.
  }

  /**
   * @param $value
   * @return boolean
   */
  public function isYear($value) {
    // TODO: Implement isYear() method.
  }

  /**
   * @param $value
   * @return boolean
   */
  public function isUnixTimeStamp($value) {
    // TODO: Implement isUnixTimeStamp() method.
  }

  /**
   * @param $value
   * @return $this
   */
  public function morphDate(&$value) {
    // TODO: Implement morphDate() method.
  }

  /**
   * @param $value
   * @return $this
   */
  public function morphText(&$value) {
    // TODO: Implement morphText() method.
  }

  /**
   * @param $value
   * @return $this
   */
  public function morphList(&$value) {
    // TODO: Implement morphList() method.
  }

  /**
   * @param $value
   * @return $this
   */
  public function morphUnique(&$value) {
    // TODO: Implement morphUnique() method.
  }
}