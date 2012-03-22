<?php

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2012 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5.3
 * @copyright  Leo Feyer 2005-2012
 * @author     Leo Feyer <http://www.contao.org>
 * @package    Backend
 * @license    LGPL
 */


/**
 * Run in a custom namespace, so the class can be replaced
 */
namespace Contao;


/**
 * Class FileTree
 *
 * Provide methods to handle input field "page tree".
 * @copyright  Leo Feyer 2005-2012
 * @author     Leo Feyer <http://www.contao.org>
 * @package    Controller
 */
class FileTree extends \Widget
{

	/**
	 * Submit user input
	 * @var boolean
	 */
	protected $blnSubmitInput = true;

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'be_widget';

	/**
	 * Order ID
	 * @var string
	 */
	protected $strOrderId;

	/**
	 * Order name
	 * @var string
	 */
	protected $strOrderName;

	/**
	 * Gallery flag
	 * @var boolean
	 */
	protected $blnIsGallery = false;


	/**
	 * Load the database object
	 * @param array
	 */
	public function __construct($arrAttributes=null)
	{
		$this->import('Database');
		parent::__construct($arrAttributes);

		// Prepare the orderSRC field
		$this->strOrderId = str_replace('multiSRC', 'orderSRC', $this->strId);
		$this->strOrderName = str_replace('multiSRC', 'orderSRC', $this->strName);

		// Retrieve the orderSRC value
		$objRow = $this->Database->prepare("SELECT type, orderSRC FROM {$this->strTable} WHERE id=?")
					   ->limit(1)
					   ->execute($this->Input->get('id'));

		$this->orderSRC = $objRow->orderSRC;
		$this->blnIsGallery = ($objRow->type == 'gallery');
	}


	/**
	 * Return an array if the "multiple" attribute is set
	 * @param mixed
	 * @return mixed
	 */
	protected function validator($varInput)
	{
		// Store the orderSRC value
		$this->Database->prepare("UPDATE {$this->strTable} SET orderSRC=? WHERE id=?")
					   ->execute($this->Input->post($this->strOrderName), $this->Input->get('id'));

		// Return the value as usual
		if (strpos($varInput, ',') === false)
		{
			return $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['eval']['multiple'] ? array(intval($varInput)) : intval($varInput);
		}
		else
		{
			return array_map('intval', array_filter(explode(',', $varInput)));
		}
	}


	/**
	 * Generate the widget and return it as string
	 * @return string
	 */
	public function generate()
	{
		$strValues = '';
		$arrValues = array();

		if ($this->varValue != '')
		{
			$strValues = implode(',', array_map('intval', (array)$this->varValue));
			$objFiles = $this->Database->execute("SELECT id, path, type FROM tl_files WHERE id IN($strValues) ORDER BY " . $this->Database->findInSet('id', $strValues));

			while ($objFiles->next())
			{
				// File system and database seem not in sync
				if (!file_exists(TL_ROOT . '/' . $objFiles->path))
				{
					continue;
				}

				// Image galleries
				if ($this->blnIsGallery)
				{
					if ($objFiles->type == 'folder')
					{
						$objSubfiles = \FilesCollection::findBy('pid', $objFiles->id);

						if ($objSubfiles === null)
						{
							continue;
						}

						while ($objSubfiles->next())
						{
							// Skip subfolders
							if ($objSubfiles->type == 'folder')
							{
								continue;
							}

							$objFile = new \File($objSubfiles->path);

							if (!$objFile->isGdImage)
							{
								continue;
							}

							$arrValues[$objSubfiles->id] = $this->generateImage($this->getImage($objSubfiles->path, 50, 50, 'center_center'), '', 'class="gimage"');
						}
					}
					else
					{
						$objFile = new \File($objFiles->path);

						if ($objFile->isGdImage)
						{
							$arrValues[$objFiles->id] = $this->generateImage($this->getImage($objFiles->path, 50, 50, 'center_center'), '', 'class="gimage"');
						}
					}
				}
				// Other element types
				else
				{
					if ($objFiles->type == 'folder')
					{
						$arrValues[$objFiles->id] = $this->generateImage('folderC.gif') . ' ' . $objFiles->path;
					}
					else
					{
						$objFile = new \File($objFiles->path);
						$arrValues[$objFiles->id] = $this->generateImage($objFile->icon) . ' ' . $objFiles->path;
					}
				}
			}

			// Apply a custom sort order
			if ($this->orderSRC != '')
			{
				$arrNew = array();
				$arrOrder = array_map('intval', explode(',', $this->orderSRC));

				foreach ($arrOrder as $i)
				{
					if (isset($arrValues[$i]))
					{
						$arrNew[$i] = $arrValues[$i];
						unset($arrValues[$i]);
					}
				}

				if (!empty($arrValues))
				{
					foreach ($arrValues as $k=>$v)
					{
						$arrNew[$k] = $v;
					}
				}

				$arrValues = $arrNew;
				unset($arrNew);
			}
		}

		$return = '<input type="hidden" name="'.$this->strName.'" id="ctrl_'.$this->strId.'" value="'.$strValues.'">
  <input type="hidden" name="'.$this->strOrderName.'" id="ctrl_'.$this->strOrderId.'" value="'.$this->orderSRC.'">
  <div class="selector_container" id="target_'.$this->strId.'">
    <ul id="sort_'.$this->strId.'" class="sortable'.($this->blnIsGallery ? ' sgallery' : '').'">';

		foreach ($arrValues as $k=>$v)
		{
			$return .= '<li data-id="'.$k.'">'.$v.'</li>';
		}

		$return .= '</ul>
    <p><a href="contao/file.php?table='.$this->strTable.'&amp;field='.$this->strField.'&amp;id='.$this->Input->get('id').'&amp;value='.$strValues.'" class="tl_submit" onclick="Backend.getScrollOffset();Backend.openModalSelector({\'width\':765,\'title\':\''.$GLOBALS['TL_LANG']['MOD']['files'][0].'\',\'url\':this.href,\'id\':\''.$this->strId.'\'});return false">'.$GLOBALS['TL_LANG']['MSC']['changeSelection'].'</a></p>
    <script>Backend.makeGallerySortable("sort_'.$this->strId.'", "ctrl_'.$this->strOrderId.'");</script>
  </div>';

		return $return;
	}
}
