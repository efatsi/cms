<?php

/**
 *
 */
abstract class bBaseController extends CController
{
	/**
	 * Returns the directory containing view files for this controller.
	 * We're overriding this since CController's version defaults $module to Yii::app().
	 * @return string the directory containing the view files for this controller.
	 */
	public function getViewPath()
	{
		if (($module = $this->getModule()) === null)
			$module = Blocks::app();

		return $module->getViewPath().'/';
	}

	public function getViewFile($viewName)
	{
		if (($theme = Blocks::app()->getTheme()) !== null && ($viewFile = $theme->getViewFile($this, $viewName)) !== false)
			return $viewFile;

		$moduleViewPath = $basePath = Blocks::app()->getViewPath();
		if (($module = $this->getModule()) !== null)
			$moduleViewPath = $module->getViewPath();

		return $this->resolveViewFile($viewName, $this->getViewPath(), $basePath, $moduleViewPath);
	}

	/**
	 * Loads a template
	 * @param       $relativeTemplatePath
	 * @param array $data Any variables that should be available to the template
	 * @param bool  $return Whether to return the results, rather than output them
	 * @return mixed
	 */
	public function loadTemplate($relativeTemplatePath, $data = array(), $return = false)
	{
		if (!is_array($data))
			$data = array();

		foreach ($data as &$tag)
		{
			$tag = bTemplateHelper::getVarTag($tag);
		}

		$baseTemplatePath = Blocks::app()->path->normalizeTrailingSlash(Blocks::app()->viewPath);

		if (bTemplateHelper::findFileSystemMatch($baseTemplatePath, $relativeTemplatePath) !== false)
			return $this->renderPartial($relativeTemplatePath, $data, $return);

		throw new bHttpException(404);
	}

	/**
	 * @param $relativeTemplatePath
	 * @param array $data
	 * @return mixed
	 */
	public function loadEmailTemplate($relativeTemplatePath, $data = array())
	{
		if (!is_array($data))
			$data = array();

		foreach ($data as &$tag)
		{
			$tag = bTemplateHelper::getVarTag($tag);
		}

		$baseTemplatePath = Blocks::app()->path->normalizeTrailingSlash(Blocks::app()->path->emailTemplatePath);

		if (bTemplateHelper::findFileSystemMatch($baseTemplatePath, $relativeTemplatePath) !== false)
			return $this->renderPartial($relativeTemplatePath, $data, true);

		// exception?
	}

	/**
	 * Returns a 404 if this isn't a POST request
	 */
	public function requirePostRequest()
	{
		if (!Blocks::app()->getConfig('devMode') && Blocks::app()->request->requestType !== 'POST')
			throw new bHttpException(404);
	}

	/**
	 * Returns a 404 if this isn't an Ajax request
	 */
	public function requireAjaxRequest()
	{
		if (!Blocks::app()->getConfig('devMode') && !Blocks::app()->request->isAjaxRequest)
			throw new bHttpException(404);
	}
}
