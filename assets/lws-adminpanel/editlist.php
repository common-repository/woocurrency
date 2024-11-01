<?php
namespace LWS\Adminpanel;
if( !defined( 'ABSPATH' ) ) exit();
if( !class_exists("\\" . __NAMESPACE__ . "\\EditList") ) :

require_once 'editlist/abstract-source.php';
require_once 'editlist/class-pager.php';
require_once 'editlist/class-filter.php';
require_once 'editlist/abstract-action.php';

/** As post, display a list of item with on-the-fly edition. */
class EditList
{
	const FIX = 0x00; /// read only list
	const MOD = 0x01; /// allows row modification only
	const DEL = 0x02; /// allows delete row
	const DUP = 0x04; /// allows creation of new record via copy of existant
	const ADD = 0x08; /// allows creation of new record from scratch
	const DDD = self::MOD | self::DEL | self::DUP; /// eDit, Duplicate and Delete
	const MDA = self::MOD | self::DEL | self::ADD; /// Edit, Delete and Add
	const ALL = 0x0F; /// Allows all modification, equivalent to MOD | ADD | DEL | DUP

	private $KeyAction = 'action-uid';

	/**
	 * @param $editionId (string) is a unique id which refer to this EditList.
	 * @param $recordUIdKey (string) is the key which will be used to ensure record unicity.
	 * @param $source instance which etends EditListSource.
	 * @param $mode allows list for modification (use bitwise operation, @see ALL)
	 * @param $filtersAndActions an array of instance of EditList\Action or EditList\Filter. */
	public function __construct( $editionId, $recordUIdKey, $source, $mode = self::ALL, $filtersAndActions=array() )
	{
		$this->slug = sanitize_key($editionId);
		$this->m_Id = esc_attr($editionId);
		$this->m_UId = esc_attr($recordUIdKey);

		if( $this->m_UId != $recordUIdKey )
			error_log("!!! $recordUIdKey is not safe to be used as record key (html escape = {$this->m_UId}).");

		$sourceClass = __NAMESPACE__ . '\EditList\Source';
		if( !is_a($source, $sourceClass) )
			error_log("!!! EditList data source is not a $sourceClass");
		else
			$this->m_Source = $source;

		$this->m_Mode = $mode;
		$this->m_PageDisplay = new EditList\Pager($this->m_Id);

		if( !is_array($filtersAndActions) )
			$filtersAndActions = array($filtersAndActions);

		$this->m_Actions = array();
		$this->m_Filters = array();
		foreach( $filtersAndActions as $faa )
		{
			if( is_a($faa, __NAMESPACE__ . '\EditList\Action') )
				$this->m_Actions[] = $faa;
			else if( is_a($faa, __NAMESPACE__ . '\EditList\Filter') )
				$this->m_Filters[] = $faa;
		}
		//$this->applyActions();
		add_action('init', array($this, 'manageActions'), 0);

		add_action('wp_ajax_lws_adminpanel_editlist', array($this, 'ajax'));
	}

	public function manageActions()
	{
		$this->m_Actions = \apply_filters('lws_adminpanel_editlist_actions_'.$this->slug, $this->m_Actions);
		$this->applyActions();
	}

	public function ajax()
	{
		if( isset($_REQUEST['id']) && isset($_REQUEST['method']) && isset($_REQUEST['line']) )
		{
			$method = $_REQUEST['method'];
			if( !in_array($method, self::methods()) )
				exit(0);

			$id = $_REQUEST['id'];
			$line = $_REQUEST['line'];
			if( empty($id) || empty($line) )
				exit(0);

			$up = $this->accept($id, $method, $line);
			if( !is_null($up) )
			{
				wp_send_json($up);
				exit();
			}
		}
	}

	/** Display list by page (default is true)
	 * @return this */
	public function setPageDisplay($yes=true)
	{
		if( $yes === false || is_null($yes) )
			$this->m_PageDisplay = null;
		else if( $yes === true )
			$this->m_PageDisplay = new EditList\Pager($this->m_Id);
		else if( is_a($yes, __NAMESPACE__ . '\EditList\Pager') )
			$this->m_PageDisplay = $yes;
		else
			$this->m_PageDisplay = null;
		return $this;
	}

	/**	Echo the list as a <table> */
	public function display()
	{
		$rcount = -1;
		$limit = null;
		$this->filters($rcount, $limit, true);

		$head = $this->completeLabels(\apply_filters('lws_adminpanel_editlist_labels_'.$this->slug, $this->m_Source->labels()));
		$chead = count($head);
		$popup = "";
		if( isset($this->actionResult) && !empty($this->actionResult) )
			$popup = " data-popup='" . base64_encode($this->actionResult) . "'";
		echo "<table class='wp-list-table widefat fixed striped lws-editlist' id='{$this->m_Id}' uid='{$this->m_UId}'$popup>";
		echo "<thead>";
		$this->editionHead($head);
		echo "</thead><tbody>";

		echo $this->editionForm($chead);
		$this->editionLine(array(), $head);
		$table = \apply_filters('lws_adminpanel_editlist_read_'.$this->slug, $this->m_Source->read($limit));
		foreach( $table as $tr )
			$this->editionLine($tr, $head);

		echo "</tbody><tfoot>";
		$this->editionHead($head);
		echo "</tfoot>";
		echo "</table>";

		if( !empty($this->m_Actions) )
			$this->addActions($this->m_Actions);
		$this->addButton();
	}

	protected function filters(&$rcount, &$limit, $above=true)
	{
		$class = $above ? " lws-editlist-above" : " lws-editlist-below";
		if( !is_null($this->m_PageDisplay) )
		{
			echo "<div class='lws-editlist-filters$class {$this->m_Id}-filters'>";
			echo "<div class='lws-editlist-filters-first-line'>";
			foreach( \apply_filters('lws_adminpanel_editlist_filters_'.$this->slug, $this->m_Filters) as $filter )
			{
				$c = $filter->cssClass();
				echo "<div class='$c'>";
				echo $filter->input();
				echo "</div>";
			}
			if( is_null($limit) )
			{
				$rcount = \apply_filters('lws_adminpanel_editlist_total_'.$this->slug, $this->m_Source->total());
				$limit = $this->m_PageDisplay->readLimit($rcount);
			}
			echo "</div>";
			echo $this->m_PageDisplay->navDiv($rcount, $limit);
			echo "</div>";
		}
	}

	protected function addActions()
	{
		$ph = __('Apply', 'lws-adminpanel');
		echo "<div class='lws-editlist-actions'>";
		foreach( $this->m_Actions as $action )
		{
			echo "<div class='lws-editlist-action' data-id='{$this->m_Id}'>";
			echo "<input type='hidden' name='{$this->KeyAction}' value='{$action->UID}'>";
			echo $action->input();
			echo "<button class='lws-adm-btn lws-editlist-action-trigger'>$ph</button>";
			echo "</div>";
		}
		echo "</div>";
	}

	protected function completeLabels($lab)
	{
		foreach( array_keys($lab) as $k )
		{
			if( !is_array( $lab[$k] ) )
				$lab[$k] = array($lab[$k]);
			while( count($lab[$k]) < 2 )
				$lab[$k][] = '';
		}
		return $lab;
	}

	protected function addButton()
	{
		$buttons = array();
		if( $this->m_Mode & self::ADD )
		{
			$ph = __("Add", 'lws-adminpanel');
			$buttons[] = "<button class='lws-adm-btn lws-editlist-add' data-id='{$this->m_Id}'>$ph</button>";
		}
		$buttons = apply_filters('lws_ap_editlist_buttons_'.$this->slug, $buttons, $this->m_Mode);
		foreach($buttons as $button)
		{
			echo $button;
		}
	}

	protected function editionHead($head)
	{
		$chead = count($head);
		echo "<tr class='lws-editlist-header lws-editlist-row'>";
		$first = ' column-title column-primary';
		if( !empty($this->m_Actions) )
		{
			$width = " style='width:20px'";
			$chk = "<input type='checkbox' name='lws-editlist-selectall' class='lws-ignore-confirm'>";
			echo "<th class='lws-editlist-cell lws-editlist-checkbox manage-column$first'$width>$chk</th>";
			$first = '';
		}
		foreach( $head as $key => $label )
		{
			$width = '';
			if( !empty($label[1]) )
				$width = " style='width:{$label[1]}'";
			echo "<th class='lws-editlist-cell manage-column$first' data-key='$key'$width>{$label[0]}</th>";
			$first = '';
		}
		echo "</tr>";
	}

	protected function editionLine($tr, $head)
	{
		if( empty($tr) )
		{
			// template line
			$data = "";
			$dft = \apply_filters('lws_adminpanel_editlist_default_'.$this->slug, $this->m_Source->defaultValues());
			if( !empty($dft) && is_array($dft) )
			{
				$decode = array();
				foreach( $dft as $k => $v )
					$decode[$k] = html_entity_decode($v);
				$data = base64_encode(json_encode($decode));
			}
			echo "<tr data-template='1' class='lws-editlist-row lws-editlist-template' data-line='$data' style='display:none'>";
		}
		else
		{
			$decode = array();
			foreach( $tr as $k => $v )
				$decode[$k] = html_entity_decode($v);
			$data = base64_encode(json_encode($decode));
			echo "<tr class='lws-editlist-row lws-editlist-row-editable' data-line='$data'>";
		}
		if( !empty($this->m_Actions) )
		{
			$id = "";
			if( isset($tr[$this->m_UId]) )
			{
				$encoded = base64_encode($tr[$this->m_UId]);
				$id = " data-id='$encoded'";
			}
			$chk = "<input type='checkbox' name='lws-editlist-selectitem'$id class='lws-ignore-confirm'>";
			echo "<th class='lws-editlist-cell lws-editlist-checkbox'>$chk</th>";
		}
		$first = true;
		foreach( $head as $id => $td )
		{
			$cell = isset($tr[$id]) ? $tr[$id] : '';
			echo "<td class='lws-editlist-cell' data-key='$id'>$cell";
			if( $first )
				echo $this->editButtons(isset($tr[$this->m_UId]) ? $tr[$this->m_UId] : null);
			$first = false;
			echo "</td>";
		}
		echo "</tr>";
	}

	protected function editionForm($colspan=1)
	{
		$firstCol = "";
		if( !empty($this->m_Actions) )
			$firstCol = "<td></td>";

		$form = "<tr class='lws-editlist-row' style='display:none'>$firstCol<td colspan='$colspan'></td></tr>";
		$form .= "<tr class='lws-editlist-row lws-editlist-line-form' style='display:none'>$firstCol<td colspan='$colspan'>";

		$form .= "<div class='lws-editlist-line-inputs'>";
		$form .= \apply_filters('lws_adminpanel_editlist_input_'.$this->slug, $this->m_Source->input());
		$form .= "</div>";

		$ph = array(__('Cancel', 'lws-adminpanel'), __('Save', 'lws-adminpanel'));
		$form .= "<div class='lws-editlist-line-btns'>";
		$form .= "<button class='button lws-adm-btn lws-editlist-btn-cancel'>{$ph[0]}</button>";
		$form .= "<span class='lws-hspan'></span>";
		$form .= "<button class='button lws-adm-btn lws-editlist-btn-save'>{$ph[1]}</button>";
		$form .= "</div>";

		$form .= "</td></tr>";
		return $form;
	}

	// the button line which appear under each line.
	protected function editButtons($id)
	{
		$sep = "<span class='lws-editlist-btn-sep'>|</span>";
		$ph = apply_filters('lws_ap_editlist_item_action_names_' . $this->slug, array(
			self::DEL => __('Trash', 'lws-adminpanel'),
			self::DUP => __('Copy', 'lws-adminpanel'),
			self::MOD => __('Quick Edit')
		));
		$row = '';
		$btns = '';

		if( $this->m_Mode & self::DEL )
			$btns .= "<span class='lws-editlist-btn-del'>{$ph[self::DEL]}</span>";
		if( $this->m_Mode & self::DUP )
			$btns .= (empty($btns)?'':$sep)."<span class='lws-editlist-btn-dup'>{$ph[self::DUP]}</span>";
		if( $this->m_Mode & self::MOD )
			$btns .= (empty($btns)?'':$sep)."<span class='lws-editlist-btn-mod'>{$ph[self::MOD]}</span>";

		$btns = apply_filters('lws_ap_editlist_item_buttons_' . $this->slug, $btns, $sep, $id);

		if( !empty($btns) )
			$row = "<br/><div class='lws-editlist-buttons' style='visibility: hidden;'>$btns</div>";
		return $row;
	}

	/// @return an array with accepted method value.
	static public function methods()
	{
		return array("put", "del");
	}

	/**	Test if this instance is concerne (based on $editionId),
	 *	then save the $line. @see write().
	 * 	or return a list of the lines. @see read().
	 * 	or delete a line. @see erase().
	 * 	or null if not concerned.
	 *	ajax {action: 'editlist', method: 'put', id: "?", line: {json ...}} */
	public function accept($editionId, $method, $line)
	{
		if( $editionId === $this->m_Id )
		{
			$data = json_decode( base64_decode($line), true );
			if( $method === "put" )
			{
				$result = array( "status" => 0 );
				$data = \apply_filters('lws_adminpanel_editlist_write_'.$this->slug, $this->m_Source->write($data));
				if( \is_wp_error($data) )
				{
					$result["error"] = $data->get_error_message();
				}
				else if( \LWS\Adminpanel\EditList\UpdateResult::isA($data) )
				{
					$result["status"] = $data->success ? 1 : 0;
					if( $data->success )
					{
						$result["line"] = base64_encode(json_encode($data->data));
						if( !empty($data->message) )
							$result["message"] = $data->message;
					}
					else if( !empty($data->message) )
						$result["error"] = $data->message;
				}
				else if( $data !== false )
				{
					$result["status"] = 1;
					$result["line"] = base64_encode(json_encode($data));
				}
				return $result;
			}
			else if( $method === "del" )
			{
				return array( "status" => (\apply_filters('lws_adminpanel_editlist_erase_'.$this->slug, $this->m_Source->erase($data)) ? 1 : 0) );
			}
		}
		return null;
	}

	/** If any local action match the posted action uid,
	 * we apply it on the posted selection.
	 * Then, unset the uid from $_POST to ensure it is done only once. */
	protected function applyActions()
	{
		$keyItems = 'action-items';
		if( isset($_POST[$this->KeyAction]) && !empty($_POST[$this->KeyAction])
			&& isset($_POST[$keyItems]) && !empty($_POST[$keyItems]) )
		{
			$uid = sanitize_key($_POST[$this->KeyAction]);
			$items = json_decode( base64_decode($_POST[$keyItems]), true );
			foreach( $this->m_Actions as $action )
			{
				if( $uid == $action->UID )
				{
					$ret = $action->apply($items);
					if( !empty($ret) && is_string($ret) )
						$this->actionResult = $ret;
					unset($_POST[$this->KeyAction]);
					break;
				}
			}
		}
	}

}

endif
?>
