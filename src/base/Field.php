<?php
/**
 * @link http://buildwithcraft.com/
 * @copyright Copyright (c) 2013 Pixel & Tonic, Inc.
 * @license http://buildwithcraft.com/license
 */

namespace craft\app\base;

use Craft;
use craft\app\elements\db\ElementQuery;
use craft\app\elements\db\ElementQueryInterface;
use craft\app\elements\MatrixBlock;
use craft\app\helpers\DbHelper;
use craft\app\helpers\StringHelper;
use Exception;
use yii\base\ErrorHandler;
use yii\db\Schema;

/**
 * Field is the base class for classes representing fields in terms of objects.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
abstract class Field extends SavableComponent implements FieldInterface
{
	// Traits
	// =========================================================================

	use FieldTrait;

	// Static
	// =========================================================================

	/**
	 * @inheritdoc
	 */
	public static function hasContentColumn()
	{
		return true;
	}

	// Properties
	// =========================================================================

	/**
	 * @var bool Whether the field is fresh.
	 * @see isFresh()
	 * @see setIsFresh()
	 */
	private $_isFresh;

	// Public Methods
	// =========================================================================

	/**
	 * Use the translated field name as the string representation.
	 *
	 * @return string
	 */
	public function __toString()
	{
		try
		{
			return Craft::t('app', $this->name);
		}
		catch (Exception $e)
		{
			ErrorHandler::convertExceptionToError($e);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		$rules = [
			[['name', 'handle'], 'required'],
			[['groupId'], 'number', 'min' => -2147483648, 'max' => 2147483647, 'integerOnly' => true],
		];

		// Only validate the ID if it's not a new field
		if ($this->id !== null && strncmp($this->id, 'new', 3) !== 0)
		{
			$rules[] = [['id'], 'number', 'min' => -2147483648, 'max' => 2147483647, 'integerOnly' => true];
		}

		return $rules;
	}

	/**
	 * @inheritdoc
	 */
	public function getContentColumnType()
	{
		return Schema::TYPE_STRING;
	}

	/**
	 * @inheritdoc
	 */
	public function beforeSave()
	{
	}

	/**
	 * @inheritdoc
	 */
	public function afterSave()
	{
	}

	/**
	 * @inheritdoc
	 */
	public function beforeDelete()
	{
	}

	/**
	 * @inheritdoc
	 */
	public function afterDelete()
	{
	}

	/**
	 * @inheritdoc
	 */
	public function getInputHtml($value, $element)
	{
		return '<textarea name="'.$this->handle.'">'.$value.'</textarea>';
	}

	/**
	 * @inheritdoc
	 */
	public function getStaticHtml($value, $element)
	{
		// Just return the input HTML with disabled inputs by default
		Craft::$app->templates->startJsBuffer();
		$inputHtml = $this->getInputHtml($value, $element);
		$inputHtml = preg_replace('/<(?:input|textarea|select)\s[^>]*/i', '$0 disabled', $inputHtml);
		Craft::$app->templates->clearJsBuffer();
		return $inputHtml;
	}

	/**
	 * @inheritdoc
	 */
	public function validateValue($value, $element)
	{
		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function beforeElementSave(ElementInterface $element)
	{
		$value = $this->getElementValue($element);
		$value = $this->prepareValueBeforeSave($value, $element);
		$this->setElementValue($element, $value);
	}

	/**
	 * @inheritdoc
	 */
	public function afterElementSave(ElementInterface $element)
	{
	}

	/**
	 * @inheritdoc
	 */
	public function getSearchKeywords($value, $element)
	{
		return StringHelper::toString($value, ' ');
	}

	/**
	 * @inheritdoc
	 */
	public function prepareValue($value, $element)
	{
		return $value;
	}

	/**
	 * @inheritdoc
	 */
	public function modifyElementsQuery(ElementQueryInterface $query, $value)
	{
		if ($value !== null)
		{
			if (self::hasContentColumn())
			{
				$handle = $this->handle;
				/** @var ElementQuery $query */
				$query->subQuery->andWhere(DbHelper::parseParam('content.'.Craft::$app->content->fieldColumnPrefix.$handle, $value, $query->subQuery->params));
			}
			else
			{
				return false;
			}
		}
	}

	/**
	 * @inheritdoc
	 */
	public function setIsFresh($isFresh)
	{
		$this->_isFresh = $isFresh;
	}

	/**
	 * @inheritdoc
	 */
	public function getGroup()
	{
		return Craft::$app->fields->getGroupById($this->groupId);
	}

	// Protected Methods
	// =========================================================================

	/**
	 * Returns the location in POST that this field's content was pulled from.
	 *
	 * @param ElementInterface|Element $element The element this field is associated with
	 * @return string|null
	 */
	protected function getContentPostLocation($element)
	{
		if ($element)
		{
			$elementContentPostLocation = $element->getContentPostLocation();

			if ($elementContentPostLocation)
			{
				return $elementContentPostLocation.'.'.$this->handle;
			}
		}

		return null;
	}

	/**
	 * Returns this field’s value on a given element.
	 *
	 * @param ElementInterface|Element $element The element
	 * @return mixed The field’s value
	 */
	protected function getElementValue(ElementInterface $element)
	{
		$handle = $this->handle;
		return $element->getContent()->$handle;
	}

	/**
	 * Updates this field’s value on a given element.
	 *
	 * @param ElementInterface|Element $element The element
	 * @param mixed                    $value The field’s new value
	 */
	protected function setElementValue(ElementInterface $element, $value)
	{
		$handle = $this->handle;
		$element->getContent()->$handle = $value;
	}

	/**
	 * Prepares this field’s value on an element before it is saved.
	 *
	 * @param mixed                    $value   The field’s raw POST value
	 * @param ElementInterface|Element $element The element that is about to be saved
	 * @return mixed The field’s prepared value
	 */
	protected function prepareValueBeforeSave($value, $element)
	{
		return $value;
	}

	/**
	 * Returns whether this is the first time the element's content has been edited.
	 *
	 * @param ElementInterface|Element|null $element
	 * @return bool
	 */
	protected function isFresh($element)
	{
		if (!isset($this->_isFresh))
		{
			// If this is for a Matrix block, we're more interested in its owner
			if (isset($element) && $element instanceof MatrixBlock)
			{
				$element = $element->getOwner();
			}

			$this->_isFresh = (!$element || (empty($element->getContent()->id) && !$element->hasErrors()));
		}

		return $this->_isFresh;
	}
}
