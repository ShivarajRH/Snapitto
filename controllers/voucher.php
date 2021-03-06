<?php
class Voucher extends Controller {
	
	function __construct()
	{
		parent::__construct();	
		$this->task_status = $this->config->item('task_status');
		$this->db->query("set session group_concat_max_len=5000000;");
		$this->task_for=$this->config->item('task_for');
		$this->load->library('pagination');
	}
	
	//------------couponmodule---------------
	/**
	 * function for config the pnh franchise prepaid menu
	 */
	function pnh_prepaid_menus()
	{
		$this->erpm->auth(ADMINISTRATOR_ROLE);
		$data['pnh_menu_list']=$this->db->query("select a.*,b.is_active from pnh_menu a left join pnh_prepaid_menu_config b on b.menu_id=a.id where a.status=1 order by a.name")->result_array();
		$data['page']='pnh_config_prepaid_menus';
		$this->load->view('admin',$data);
	}
	
	function do_config_prepaid_menu()
	{
		$user=$this->erpm->auth(ADMINISTRATOR_ROLE);
		$menu_ids=$this->input->post('is_prepaid');
	
		$param1=array();
		$param1['is_active']=0;
		$param1['modified_by']=$user['userid'];
		$param1['modified_on']=cur_datetime();
		$this->db->update("pnh_prepaid_menu_config",$param1);
	
		if($menu_ids)
		{
			foreach($menu_ids as $menu)
			{
				$already=$this->db->query("select count(*) as ttl from pnh_prepaid_menu_config where menu_id=?",$menu)->row()->ttl;
	
				if($already)
				{
					$this->db->query("update pnh_prepaid_menu_config set is_active=1 where menu_id=?", $menu);
				}else{
					$param=array();
					$param['menu_id']=$menu;
					$param['created_by']=$user['userid'];
					$param['created_on']=cur_datetime();
					$this->db->insert('pnh_prepaid_menu_config',$param);
				}
			}
		}
	
		$this->session->set_flashdata("erp_pop_info","selectred menus configured for prepaid");
		redirect('admin/pnh_prepaid_menus');
	}
	
	/**
	 * function for manage the coupons
	 */
	function pnh_manage_vouchers($pg=0)
	{
		$this->erpm->auth(ADMINISTRATOR_ROLE);
		$coupon_details=array();
		$limit=5;
	
		$sql="select a.created_on,sum(b.denomination) as value,c.name,a.group_code
					from pnh_t_voucher_details a
					join pnh_m_voucher b on b.voucher_id=a.voucher_id
					join king_admin c on c.id=a.created_by
					group by group_code
					order by created_on desc limit $pg , $limit";
	
		$coupon_details['coupons_list']=$this->db->query($sql)->result_array();
		$coupon_details['total_coupons']=$this->db->query("select count(*) as ttl from pnh_t_voucher_details")->row()->ttl;
		$coupon_details['total_value']=$this->db->query("select ifnull(sum(value),0) as ttl_val from pnh_t_voucher_details")->row()->ttl_val;
		$coupon_details['total_alloted']=$this->db->query("select count(*) as ttl_alloted from pnh_t_voucher_details where status=?",1)->row()->ttl_alloted;
		$coupon_details['total_assigned']=$this->db->query("select count(*) as ttl_assigned from pnh_t_voucher_details where status=?",2)->row()->ttl_assigned;
		$coupon_details['total_records']=count($this->db->query("select * from  pnh_t_voucher_details group by group_code ")->result_array());
		//pagination end
		$config['base_url'] = site_url("admin/pnh_manage_vouchers");
		$config['total_rows'] = $coupon_details['total_records'];
		$config['per_page'] = $limit;
		$config['uri_segment'] = 3;
		$this->config->set_item('enable_query_strings',false);
		$this->pagination->initialize($config);
		$coupon_details['pagination'] = $this->pagination->create_links();
		$this->config->set_item('enable_query_strings',true);
		//pagination end
		$data['coupon_details']=$coupon_details;
		$data['page']='pnh_voucher_details';
		$this->load->view("admin",$data);
	}
	
	/**
	 * function for create voucher form page
	 */
	function pnh_create_voucher()
	{
		$user=$this->erpm->auth(ADMINISTRATOR_ROLE);
		$data['vouchers_list']=$this->db->query("select * from pnh_m_voucher order by denomination asc")->result_array();
		$data['page']='pnh_create_voucher';
		$this->load->view('admin',$data);
	}
	/**
	 * function for add a new coupons
	 */
	function pnh_add_voucher()
	{
		$user=$this->erpm->auth(ADMINISTRATOR_ROLE);
	
		if(!$_POST)
			die();
	
		$voucher_values=$this->input->post('denomination');
		$voucher_qty=$this->input->post("require_qty");
		$voucher_ids=$this->input->post("voucher_id");
		
		//generate the group code
		$pre_grp_code=$this->db->query("select ifnull(max(group_code),0) as grp_code from pnh_t_voucher_details;")->row()->grp_code ;
		
		if(!$pre_grp_code)
			$pre_grp_code=1;
		else
			$pre_grp_code+=1;
	
		$total_vouchers_value=0;
		$sub_total=0;
		foreach($voucher_ids as $i=>$vid)
		{
			$require_qty=$voucher_qty[$i];
			$voucher_val=$voucher_values[$i];
			$total_vouchers_value=$total_vouchers_value+($voucher_val*$require_qty);
			if($require_qty==0 || $require_qty=='')
				continue;
			
			//generate voucher seirel number
			$prev_voucher_sino=$this->db->query("select ifnull(max(voucher_serial_no),0) as voucher_serial_no from pnh_t_voucher_details;")->row()->voucher_serial_no;
			
			if(!$prev_voucher_sino)
				$prev_voucher_sino=10000;
			
			for($v=1;$v<=$require_qty;$v++)
			{
				$param=array();
				$param['voucher_id']=$vid;
				$param['group_code']=$pre_grp_code;
				$param['voucher_serial_no']=$prev_voucher_sino+$v;
				$param['voucher_code']=$this->p_gen_voucher_code(13);
				$param['value']=$voucher_val;
				$param['status']=0;
				$param['created_on']=cur_datetime();
				$param['created_by']=$user['userid'];
				$this->db->insert('pnh_t_voucher_details',$param);
			}
		}
		
		$this->session->set_flashdata("erp_pop_info",'Rs '.$total_vouchers_value." value Vouchers are created");
		redirect($_SERVER['HTTP_REFERER']);
	}
	
	/**
		* function for generate a  coupon code
		* @param unknown_type $len
		* @return Ambigous <string, number>
	*/
	function p_gen_voucher_code($len)
	{
		$user=$this->erpm->auth(ADMINISTRATOR_ROLE);
		$st="";
		for($i=0;$i<$len;$i++)
			$st.=rand(1,9);
		return $st;
	}
	
	/**
	 * function for get the fresh vouchers
	 */
	function jx_get_fresh_vouchers_denomination()
	{
		$user=$this->erpm->auth(ADMINISTRATOR_ROLE);
		
		$output=array();
		$output['fresh_vouchers']=$this->db->query("select b.voucher_id,b.denomination,count(*) as ttl from pnh_t_voucher_details a join pnh_m_voucher b on b.voucher_id=a.voucher_id where a.status=0 group by b.voucher_id;")->result_array();
		
		echo json_encode($output);
	}
	
	/**
	 * function get the voucher by group
	 */
	function jx_get_vouchers_by_group()
	{
		$user=$this->erpm->auth(ADMINISTRATOR_ROLE);
		$group_id=$this->input->post('group_id');
		$output=array();
		$output['vouchers_list']=$this->db->query("select a.*,b.denomination,b.voucher_name from pnh_t_voucher_details a join pnh_m_voucher b on a.voucher_id=b.voucher_id where a.group_code=? order by b.denomination asc",$group_id)->result_array();
		echo json_encode($output);
	}
	
	/**
	 * function for download the vouchers by group
	 */
	function pnh_download_vouchers()
	{
		$user=$this->erpm->auth(ADMINISTRATOR_ROLE);
		$group_id=$this->input->post('group_id');
		
		$this->load->plugin('csv_logger');
		$csv_obj=new csv_logger_pi();
		$csv_obj->head(array('Si','Voucher name','Denomination Value','Voucher serial no','Voucher code'));
		
		$voucher_list=$this->db->query("select a.*,b.voucher_name,b.denomination from pnh_t_voucher_details a join pnh_m_voucher b on b.voucher_id=a.voucher_id where group_code=? order by b.denomination",$group_id)->result_array();
		
		foreach($voucher_list as $i=>$voucher)
		{
			$csv_obj->push(array(($i+1),$voucher['voucher_name'],$voucher['denomination'],$voucher['voucher_serial_no'],$voucher['voucher_code']));
		}
		
		$csv_obj->download('vouchers_list');
	}
	/**
	* function for create coupon book
	*/
	function pnh_create_book_template()
	{
		$user=$this->erpm->auth(ADMINISTRATOR_ROLE);
		$data['voucher_det']=$this->db->query("select * from pnh_m_voucher order by denomination")->result_array();
		$data['page']='pnh_create_book_template';
		$this->load->view('admin',$data);
	}
	
	/**
	* function for process of create coupon book
	*/
	function pnh_process_create_book_template()
	{
		$user=$this->erpm->auth(ADMINISTRATOR_ROLE);
		if(!$_POST)
			die();
		
		$this->load->library('form_validation');
		$this->form_validation->set_rules('book_name','Book Name','required');
		$this->form_validation->set_rules('book_value','Book value','required|integer|callback__validate_denomination_link');
		$need_qty=$this->input->post('need_qty');
		
		
		if($need_qty)
		foreach($need_qty as $i=>$qty)
			$this->form_validation->set_rules('need_qty['.$i.']','qty '.($i+1),'required|integer');
		
		if($this->form_validation->run() == false)
		{
			$this->pnh_create_book_template();
		}
		else
		{
			$book_name=$this->input->post('book_name');
			$book_value=$this->input->post('book_value');
			$need_qty=$this->input->post('need_qty');
			$coupon_value=$this->input->post('coupon_value');
			$voucher_ids=$this->input->post('voucher_id');
			$products=$this->input->post('pid');
			$products=implode(',',$products);
			
			$param=array();
			$param['book_type_name']=$book_name;
			$param['value']=$book_value;
			$param['product_id']=$products;
			$param['created_by']=$user['userid'];
			$param['created_on']=cur_datetime();
	
			$this->db->insert('pnh_m_book_template',$param);
			$template_id=$this->db->insert_id();
			
			foreach($need_qty as $i=>$qty)
			{
				if($qty*1==0)
					continue;
				
				$voucher_id=$voucher_ids[$i];
				$param1=array();
				$param1['book_template_id']=$template_id;
				$param1['voucher_id']=$voucher_id;
				$param1['no_of_voucher']=$qty;
				$param1['created_by']=$user['userid'];
				$param1['created_on']=cur_datetime();
				$this->db->insert("pnh_m_book_template_voucher_link",$param1);
			}
			$this->session->set_flashdata("erp_pop_info",$book_name." created for value of Rs.".formatInIndianStyle($book_value));
			redirect('admin/pnh_book_template');
		}
	
	}
	
	/**
	* function for validate denomination link
	*/
	function _validate_denomination_link($str)
	{
		$user=$this->erpm->auth(ADMINISTRATOR_ROLE);
		$book_value=$this->input->post('book_value');
		$need_qty=$this->input->post('need_qty');
		$coupon_value=$this->input->post('coupon_value');
		$template_name=trim($this->input->post('book_name'));
		$products=$this->input->post('pid');
		
		if(count($products) > 1)
		{
			$this->form_validation->set_message('_validate_denomination_link','Only one product able to link');
			return false;
		}
		
		$check_tempname=$this->db->query("select count(*) as ttl from pnh_m_book_template where trim(book_type_name) like ?",'%'.$template_name.'%')->row()->ttl;
		
		if($check_tempname)
		{
			$this->form_validation->set_message('_validate_denomination_link','Book template name already exist');
			return false;
		}
		
		$check_qty_val=0;
		foreach($need_qty as $i=>$qty)
		{
			if($qty!=0)
				$check_qty_val=1;
		}
	
		if(!$check_qty_val)
		{
			$this->form_validation->set_message('_validate_denomination_link','Qty must require');
			return false;
		}
	
	
		$total_coupon_val=0;
		foreach($need_qty as $i=>$qty)
		{
			$coupon_val=$coupon_value[$i];
			$total_coupon_val+=($coupon_val*1)*($qty*1);
		}
	
	
		if($total_coupon_val > $book_value)
		{
			$this->form_validation->set_message('_validate_denomination_link','Denomination total grater then book value');
			return false;
		}else if($total_coupon_val < $book_value){
				$this->form_validation->set_message('_validate_denomination_link','Denomination total lower then book value');
					return false;
		}else if($total_coupon_val==0)
		{
			$this->form_validation->set_message('_validate_denomination_link','Please currect denomination configuration');
			return false;
		}
		return true;
	}
	
	/**
	* function for pnh coupon template
	*/
	function pnh_book_template($pg=0)
	{
		$user=$this->erpm->auth(ADMINISTRATOR_ROLE);
		$template_details=array();
		$limit=5;
	
		$sql="select a.*,b.username from pnh_m_book_template a
					join king_admin b on b.id=a.created_by
					order by created_on desc limit $pg , $limit";
	
		$template_details['template_list']=$this->db->query($sql)->result_array();
		$template_details['total_template']=$this->db->query("select count(*) as ttl from pnh_m_book_template")->row()->ttl;
	
		//pagination end
		$config['base_url'] = site_url("admin/pnh_book_template");
		$config['total_rows'] = $template_details['total_template'];
		$config['per_page'] = $limit;
		$config['uri_segment'] = 3;
		$this->config->set_item('enable_query_strings',false);
		$this->pagination->initialize($config);
		$template_details['pagination'] = $this->pagination->create_links();
		$this->config->set_item('enable_query_strings',true);
		//pagination end
		$data['template_details']=$template_details;
		$data['page']='pnh_book_template';
		$this->load->view("admin",$data);
	}
	
	/**
	 * function for manage the voucher books
	 */
	function pnh_voucher_book()
	{
		$user=$this->erpm->auth(ADMINISTRATOR_ROLE);
		$limit=10;
		
		$data['total_books']=$this->db->query("select count(*) as ttl from pnh_t_book_details")->row()->ttl;
		
		$sql="select a.*,b.book_type_name,c.franchise_id,d.username from pnh_t_book_details a 
					join pnh_m_book_template b on b.book_template_id=a.book_template_id 
					left join  pnh_t_book_allotment c on c.book_id = a.book_id 
					join king_admin d on d.id=a.created_by
					order by a.created_on desc";
		
		$data['books_list']=$this->db->query($sql)->result_array();
		
		//pagination end
		$config['base_url'] = site_url("admin/pnh_voucher_book");
		$config['total_rows'] = $data['total_books'];
		$config['per_page'] = $limit;
		$config['uri_segment'] = 3;
		$this->config->set_item('enable_query_strings',false);
		$this->pagination->initialize($config);
		$data['pagination'] = $this->pagination->create_links();
		$this->config->set_item('enable_query_strings',true);
		//pagination end
		
		
		$data['page']='pnh_voucher_books';
		$this->load->view('admin',$data);
	}
	
	/**
	 * function for create the voucher book
	 */
	function pnh_create_voucher_book()
	{
		$user=$this->erpm->auth(ADMINISTRATOR_ROLE);
		$data['book_types']=$this->db->query("select * from pnh_m_book_template order by book_type_name")->result_array();
		$data['page']='pnh_create_voucher_book';
		$this->load->view('admin',$data);
	}
	
	function jx_get_template_denomination()
	{
		$user=$this->erpm->auth(ADMINISTRATOR_ROLE);
		$temp_id=$this->input->post('temp_id');
		$output=array();
		$output['template_denomination']=$this->db->query("select b.denomination,a.no_of_voucher from pnh_m_book_template_voucher_link a join pnh_m_voucher b on b.voucher_id=a.voucher_id where book_template_id=? order by b.denomination",$temp_id)->result_array();
		$output['template_details']=$this->db->query("select * from pnh_m_book_template where book_template_id=?",$temp_id)->row_array();
		echo json_encode($output);
	}
	
	function jx_scan_voucher_slno()
	{
		$user=$this->erpm->auth(ADMINISTRATOR_ROLE);
		$voucher_serial_no=$this->input->post('voucher_serial_no');
		$output=array();
		$voucher_det=$this->db->query("select * from pnh_t_voucher_details where voucher_serial_no=?",$voucher_serial_no)->row_array();
		
		//check if voucher exiest
		$is_exist=$this->db->query("select count(*) as ttl from pnh_t_voucher_details where voucher_serial_no=?",$voucher_serial_no)->row()->ttl;
		
		if(!$is_exist)
		{
			$output['status']='error';
			$output['msg']='Scanned voucher not found';
		}else
		{
		  $is_exist=$this->db->query("select count(*) as ttl from pnh_t_book_voucher_link where voucher_slno_id=?",$voucher_det['id'])->row()->ttl;
		  if($is_exist)
		  {
		  	$output['status']='error';
		  	$output['msg']='Scanned voucher already linked to another book';
		  }else{
		  	$output['status']='success';
		  	$output['data']=$voucher_det;
		  }
		}
		
		echo json_encode($output);
	}
	
	/**
	 * function for a create a voucher book
	 */
	function pnh_process_create_book()
	{
		$user=$this->erpm->auth(ADMINISTRATOR_ROLE);
		$book_serialno=trim($this->input->post('book_serialid'));
		$book_type=$this->input->post('book_type');
		$book_vouchers=$this->input->post('voucher_serial_number');
		$is_oddvalue_book=$this->input->post('is_oddvalue_book');
		$process=0;
		
		if($book_serialno && $book_type && $book_vouchers)
			$process=1;
		
		$already_slno=$this->db->query("select count(*) as ttl from pnh_t_book_details where trim(book_slno) = $book_serialno")->row()->ttl;
		
		
		if(!$process || $already_slno)
		{
			if(!$book_serialno)
				show_error("Please Enter Book Slno");
			else if(!$book_type)
				show_error("Please Choose Valid Book Type ");
			else if(!$book_vouchers)
				show_error("Please Scan atleast one Voucher ");
			else if($already_slno)
				show_error("Entered slno already exist");
		}else
		{
			
			//get book template details 
			$book_tmpl_res = $this->db->query("select * from pnh_m_book_template where book_template_id = ? ",$book_type);
			if(!$book_tmpl_res->num_rows())
			{
				show_error("Template not selected");
			}
			
			$book_tmpl_det = $book_tmpl_res->row_array();
			
			$is_deal = $this->db->query("select count(*) as ttl from m_product_deal_link where product_id=?",$book_tmpl_det['product_id'])->row()->ttl;
			
			if(!$is_deal)
				show_error("Do not have deal for this product please create deal");
			
			// get scanned voucher details
			$scanned_voucher_summ = array();
			$scanned_voucher_value_ttl = array();
			foreach($book_vouchers as $v_slno)
			{
				$v_det_res = $this->db->query("select a.id,a.voucher_id,voucher_serial_no,denomination from pnh_t_voucher_details a join pnh_m_voucher b on a.voucher_id = b.voucher_id where voucher_serial_no = ? ",$v_slno);
				if($v_det_res->num_rows())
				{
					$v_det = $v_det_res->row_array();
					if(!isset($scanned_voucher_summ[$v_det['voucher_id']]))
					{
						$scanned_voucher_summ[$v_det['voucher_id']] = array();
						$scanned_voucher_value_ttl[$v_det['voucher_id']] = 0;
					}
					array_push($scanned_voucher_summ[$v_det['voucher_id']],$v_det);
					$scanned_voucher_value_ttl[$v_det['voucher_id']] += $v_det['denomination'];
				}
			}
			
			$scanned_voucher_ttl_value = array_sum($scanned_voucher_value_ttl);
			
			// check if book is oldvalue book[check by scanned qty and total book value with scanned total vouchers sum]
			$is_old_value = 0;
			if($book_tmpl_det['value'] != $scanned_voucher_ttl_value)
				$is_old_value = 1;
			
			if(!$is_old_value)
			{
				$book_tmpl_voucher_link = $this->db->query("select * from pnh_m_book_template_voucher_link where book_template_id = ? ",$book_tmpl_det['book_template_id']);
				if($book_tmpl_voucher_link->num_rows())
				{
					foreach ($book_tmpl_voucher_link->result_array() as $book_tmpl_voucher_link_det)
					{
						if($book_tmpl_voucher_link_det['no_of_voucher'] != count($scanned_voucher_summ[$book_tmpl_voucher_link_det['voucher_id']]))
						{
							$is_old_value = 1;
							break;
						}
					}
				}	
			}
			
			// mark as old value book id old value book id = 1
			if($is_old_value)
				$book_tmpl_id = 1;
			else
				$book_tmpl_id = $book_tmpl_det['book_template_id'];
			
			// create book entry in master 
			$ins=array();
			$ins['book_template_id']=$book_tmpl_id;
			$ins['book_slno']=$book_serialno;
			$ins['book_value']=$book_tmpl_det['value'];
			$ins['created_by']=$user['userid'];
			$ins['created_on']=cur_datetime();
			$this->db->insert('pnh_t_book_details',$ins);
			$book_id=$this->db->insert_id();
			
			$deal_price_det = $this->db->query("select b.orgprice,b.price from m_product_deal_link a join king_dealitems b on b.id=a.itemid where product_id=?",$book_tmpl_det['product_id'])->row_array();
			$mrp=$deal_price_det['orgprice'];
			$offer_price=$deal_price_det['price'];
			
			//calculate voucher margin
			$voucher_margin=($mrp-$offer_price)/$mrp*100;
			//link book with vouchers 
			foreach($scanned_voucher_summ as $v_id=>$v_slno_list)
			{
				foreach($v_slno_list as $v_sno_det)
				{
					$ins=array();
					$ins['book_id']=$book_id;
					$ins['voucher_slno_id']=$v_sno_det['id'];
					$ins['created_by']=$user['userid'];
					$ins['created_on']=cur_datetime();
					$this->db->insert('pnh_t_book_voucher_link',$ins);
					
					$franchise_value=((100-$voucher_margin)/100)*$v_sno_det['denomination'];
					
					$this->db->query("update pnh_t_voucher_details set status = 1,voucher_margin=?,customer_value=?,franchise_value=? where status = 0 and id = ? ",array($voucher_margin,$v_sno_det['denomination'],$franchise_value,$v_sno_det['id']));
				}
			}
			
			
			$this->db->query("update t_stock_info set available_qty=available_qty+1,mrp=? where product_id=? limit 1",array($mrp,$book_tmpl_det['product_id']));
			$this->erpm->do_stock_log(1,1,$book_tmpl_det['product_id'],0,false,false,false,false);
			
			$this->db->insert("t_imei_no",array('product_id'=>$book_tmpl_det['product_id'],"imei_no"=>$book_serialno,"grn_id"=>0,"created_on"=>time(),"status"=>0));

			$this->session->set_flashdata("erp_pop_info",$book_serialno." book created ");
			redirect('admin/pnh_create_voucher_book');
		}
	}
	
	/**
	 * function for manage the book receipts
	 */
	function pnh_manage_book_allotments($pg=0)
	{
		$limit=5;
		$user=$this->erpm->auth(ADMINISTRATOR_ROLE);
		$sql="select a.*,c.franchise_name,e.book_type_name 
				from pnh_t_book_allotment a 
				join pnh_m_franchise_info c on c.franchise_id=a.franchise_id
				join pnh_t_book_details d on d.book_id=a.book_id 
				join pnh_m_book_template e on e.book_template_id=d.book_template_id
				group by a.book_id 
				order by a.created_on desc limit $pg ,$limit ";
		
		$fran_book_link_det=$this->db->query($sql)->result_array();
		$total_records=$this->db->query("select count(*) as ttl from pnh_t_book_allotment")->row()->ttl;
		
		//pagination end
		$config['base_url'] = site_url("admin/pnh_manage_book_allotments");
		$config['total_rows'] = $total_records;
		$config['per_page'] = $limit;
		$config['uri_segment'] = 3;
		$this->config->set_item('enable_query_strings',false);
		$this->pagination->initialize($config);
		$data['pagination'] = $this->pagination->create_links();
		$this->config->set_item('enable_query_strings',true);
		//pagination end
		$data['total_records']=$total_records;
		$data['page']='pnh_manage_book_allotments';
		$data['fran_book_link_det']=$fran_book_link_det;
		$this->load->view('admin',$data);
	}
	
	function jx_get_book_detby_allotment()
	{
		$user=$this->erpm->auth(ADMINISTRATOR_ROLE);
		$allotment_id=$this->input->post('allotment_id');
		$output=array();
		
		$sql="select a.id,a.allotment_id,a.book_id,a.franchise_id,b.book_value,c.book_type_name,
					ifnull(sum(d.adjusted_value),0) as payed,(b.book_value-ifnull(sum(d.adjusted_value),0)) as balance
					from pnh_t_book_allotment a
					join pnh_t_book_details b on b.book_id=a.book_id
					join pnh_m_book_template c on c.book_template_id=b.book_template_id
					left join pnh_t_book_receipt_link d on d.book_id=a.book_id
					where a.allotment_id=?
					group by a.book_id";
		
		$output['book_det']=$this->db->query($sql,$allotment_id)->result_array();
		echo json_encode($output);
	}
	
	function pnh_update_book_receipts()
	{
		$user=$this->erpm->auth(ADMINISTRATOR_ROLE);
		$receipt_ids=$this->input->post('receipt_id');
		$adjusted_values=$this->input->post('adjusted_value');
		$book_id_list=$this->input->post('book_ids');
		$franchise_list=$this->input->post('franchise_ids');
		$allotments_ids=$this->input->post('allotments_ids');
		$msg='';
		$process=1;
		$output=array();
		$output['status']='';
		$output['msg']='';
		
		//validate the payment complted or not
		foreach($book_id_list as $i=>$bookid)
		{
			//get the book value
			$book_det=$this->db->query("select a.*,b.book_type_name from pnh_t_book_details  a join pnh_m_book_template b on b.book_template_id=a.book_template_id where book_id=?",$bookid)->row_array();
			
			//get the payed value;
			$payed_det=$this->db->query("select ifnull(sum(adjusted_value),0) as payed from pnh_t_book_receipt_link where book_id=?",$bookid)->row()->payed;
			
			if($book_det['book_value']==$payed_det && isset($adjusted_values[$i]))
			{
				$output['status']='error';
				$output['msg']=$book_det['book_type_name']. ' is  book fully payed';
				$process=0;
				break;
			}
			
			if(isset($adjusted_values[$i]))
			{
				if($adjusted_values[$i]*1 > $book_det['book_value']*1)
				{
					$output['status']='error';
					$output['msg']="Your enter more then amount for the this ". $book_det['book_type_name'] ." book value";
					$process=0;
					break;
				}
				
				if($adjusted_values[$i] > ($book_det['book_value']-$payed_det))
				{
					$output['status']='error';
					$output['msg']="Your enter more then amount for the this ". $book_det['book_type_name'] ." book balance amount";
					$process=0;
					break;
				}
			}
			
		}
		
		//group the amount by receipt
		$receipt_amt_link=array();
		foreach($receipt_ids as $i=>$receipt)
		{
			if(!$adjusted_values[$i] || !is_numeric($adjusted_values[$i]))
				continue;
			
			if(!$receipt || !is_numeric($receipt ))
				continue;
			
			$is_franchise_receipt=$this->db->query("select count(*) as ttl from pnh_t_receipt_info where receipt_id=? and franchise_id=?",array($receipt,$franchise_list[$i]))->row()->ttl;
			$franchise=$this->db->query("select franchise_name from pnh_m_franchise_info where franchise_id=?",$franchise_list[$i])->row()->franchise_name;
			if(!$is_franchise_receipt)
			{
				$output['status']='error';
				$output['msg']='Receipt '. $receipt. ' not generated for  '.$franchise;
				$process=0;
				break;
			}
			
			if(!isset($receipt_amt_link[$receipt]))
				$receipt_amt_link[$receipt]=array();
			
			
			array_push($receipt_amt_link[$receipt],$adjusted_values[$i]);
		}
		
		
		$unreconciliation_amount_det=array();
		//check enter the receipt amout balance
		foreach($receipt_amt_link as $receipt_id=>$receipt_amt_det)
		{
			//check if reciept_exist
			$receipt_det_res=$this->db->query("select receipt_amount,receipt_id,franchise_id from pnh_t_receipt_info where receipt_id=?",$receipt_id);
			
			if(!$receipt_det_res->num_rows())
			{
				$output['status']='error';
				$output['msg']='Invalid receipt id';
				$process=0;
				break;
			}
			
			$receipt_det = $receipt_det_res->row_array();
			
			//check the receipt already used
			$receipt_used_amount=$this->db->query("select ifnull(sum(adjusted_value),0) as amount from pnh_t_book_receipt_link where receipt_id=?",$receipt_id)->row()->amount;
			
			
			if($receipt_det['receipt_amount'] < ($receipt_used_amount+array_sum($receipt_amt_det)))
			{
				$output['status']='error';
				$output['msg']='Do not have balance for receipt '.$receipt_id;
				$process=0;
				break;
			}
			
			$unreconciliation_amount_det[$receipt_id]=$receipt_det['receipt_amount']-($receipt_used_amount+array_sum($receipt_amt_det));
			
		}
		
		if($process)
		{
			foreach($book_id_list as $i=>$bid)
			{
				$franchise_id=$franchise_list[$i];
				$allotment_id=$allotments_ids[$i];
				$adjusted_value=$adjusted_values[$i];
				$receipt_id=trim($receipt_ids[$i]);
				
				if(!$adjusted_value || !is_numeric($adjusted_value))
					continue;
				
				if(!$receipt_id || !is_numeric($receipt_id))
					continue;
				
				if($adjusted_value && $receipt_id)
				{
					//insert the receipt link data
					$ins=array();
					$ins['book_id']=$bid;
					$ins['receipt_id']=$receipt_id;
					$ins['franchise_id']=$franchise_id;
					$ins['adjusted_value']=$adjusted_value;
					$ins['created_by']=$user['userid'];
					$ins['created_on']=cur_datetime();
					$this->db->insert("pnh_t_book_receipt_link",$ins);
					
					if($this->db->affected_rows())
					{
						$this->db->query("update pnh_t_book_allotment set status=2 where allotment_id=? and book_id=? and franchise_id=? and status=1",array($allotment_id,$bid,$franchise_id));
						
						//get the voucheres
						$vouchers_list_res=$this->db->query("select * from pnh_t_book_voucher_link where book_id=?",$bid);
						if($vouchers_list_res->num_rows())
						{
							$vouchers_list=$vouchers_list_res->result_array();
							foreach($vouchers_list as $voucher)
							{
								$this->db->query("update pnh_t_voucher_details set status=2 where id=? and status=1 limit 1",array($voucher['voucher_slno_id']));
							}
						}
						
						//update the receipt balance amount
						$this->db->query("update pnh_t_receipt_info set unreconciliation_amount=? where receipt_id=? limit 1",array($unreconciliation_amount_det[$receipt_id],$receipt_id));
					}
				}
			}
			
			$output['status']='success';
			$output['msg']='updated';
		}
		
		echo json_encode($output);
		
	}
	
	/**
	 * get the book receipt link details by book id
	 */
	function jx_get_book_receipt_link_det()
	{
		$this->erpm->auth();
		$book_id=$this->input->post('book_id');
		$output=array();
		
		$sql="select c.book_value,b.receipt_amount,a.adjusted_value,a.receipt_id 
					from pnh_t_book_receipt_link a 
					join pnh_t_receipt_info b on b.receipt_id=a.receipt_id
					join pnh_t_book_details c on c.book_id=a.book_id
					where a.book_id=?";
		
		$output['receipt_det']=$this->db->query($sql,$book_id)->result_array();
		
		echo json_encode($output);
	}
}