<?php 
	
		$order_cond = '';
		$type = $this->input->post('type');
		
		$dr_cond = '';
		
		if($type == 'all')
		{
			$sql = "select distinct c.transid,sum(o.i_coup_discount) as com,c.amount,o.transid,o.status,o.time,o.actiontime,pu.user_id as userid,pu.pnh_member_id 
								from king_orders o 
								join king_transactions c on o.transid = c.transid 
								join pnh_member_info pu on pu.user_id=o.userid 
							where c.franchise_id = ? and c.init between $st_ts and $en_ts  
							group by c.transid  
							order by c.init desc  ";
							
		}else if($type == 'shipped')
		{
			$sql = "select distinct b.transid,sum(o.i_coup_discount) as com,c.amount,o.transid,o.status,o.time,o.actiontime,pu.user_id as userid,pu.pnh_member_id 
						from shipment_batch_process_invoice_link a
						join proforma_invoices b on a.p_invoice_no = b.p_invoice_no
						join king_transactions c on c.transid = b.transid
						join king_orders o on o.id = b.order_id  
						join pnh_member_info pu on pu.user_id=o.userid 
						where c.franchise_id = ? and o.status = 2 and a.shipped = 1 and is_pnh = 1 and a.shipped_on between from_unixtime($st_ts) and from_unixtime($en_ts)   
						group by b.transid 
						order by a.shipped_on desc ;
					";
					
			$order_cond = " and a.status = 2 and d.shipped = 1 and d.shipped_on between from_unixtime($st_ts) and from_unixtime($en_ts) ";
			 
		}else if($type == 'unshipped')
		{
			$sql = "select distinct b.transid,sum(o.i_coup_discount) as com,b.amount,o.transid,o.status,o.time,o.actiontime,pu.user_id as userid,pu.pnh_member_id
							from king_orders o 
							join king_transactions b on o.transid = b.transid 
							left join proforma_invoices c on c.order_id = o.id
							left join shipment_batch_process_invoice_link d on d.p_invoice_no = c.p_invoice_no and d.shipped = 0 
							join pnh_member_info pu on pu.user_id=o.userid 
							where b.franchise_id = ? and o.status != 3  
							group by b.transid 
							order by b.init desc  ;
					";
			$order_cond = " and a.status != 3 and (d.shipped = 0 or d.shipped is null ) and t.init between $st_ts and $en_ts   ";		
		}else if($type == 'cancelled')
		{
			$sql = "select distinct b.transid,sum(o.i_coup_discount) as com,b.amount,o.transid,o.status,o.time,o.actiontime,pu.user_id as userid,pu.pnh_member_id  
						from king_orders o  
						join king_transactions b on o.transid = b.transid 
						left join proforma_invoices c on c.order_id = o.id
						left join shipment_batch_process_invoice_link d on d.p_invoice_no = c.p_invoice_no and d.shipped = 0 
						join pnh_member_info pu on pu.user_id=o.userid 
						where b.franchise_id = ? and o.status = 3  and o.actiontime between $st_ts and $en_ts   
						group by b.transid 
						order by b.init desc  ";
			$order_cond = " and a.status = 3 and a.actiontime between $st_ts and $en_ts  ";
		}
		
	  
		$res = $this->db->query($sql,array($fid));
		$order_stat=array("Confirmed","Invoiced","Shipped","Cancelled");
		
		if(!$res->num_rows())
		{
			echo "<div align='center'><h3 style='margin:2px;'>No Orders found for selected dates</h3></div>".'<table class="datagrid" width="100%"></table>';	
		}else
		{
			echo '<div align="left"><h3 style="margin:2px;" id="ttl_orders_listed">Showing <b></b> Orders from '.format_date(date('Y-m-d',$st_ts)).' to '.format_date(date('Y-m-d',$en_ts)).' </h3></div>';
				
?>		<table class="datagrid" width="100%">
	<thead><tr><th>Time</th><th>Order</th><th>Amount</th><th>Commission</th><th>Deal/Product details</th><th>Status</th><th>Last action</th></tr></thead>
	<tbody>
	
	<?php 
		 $k = 0;
		foreach($res->result_array() as $o)
		{
			$ship_dets = array(); 
					$trans_ttl_orders = 0;
					$o_item_list = $this->db->query("select e.invoice_no,d.packed,d.shipped,e.invoice_status,d.shipped_on,a.status,a.id,a.itemid,b.name,a.quantity,i_orgprice,i_price,i_discount,i_coup_discount 
																from king_orders a
																join king_dealitems b on a.itemid = b.id
																join king_transactions t on t.transid = a.transid   
																left join proforma_invoices c on c.order_id = a.id 
																left join shipment_batch_process_invoice_link d on d.p_invoice_no = c.p_invoice_no 
																left join king_invoice e on e.invoice_no = d.invoice_no and d.packed = 1 and d.shipped = 1 
															where a.transid = ? $order_cond 
															order by c.p_invoice_no desc 
													",$o['transid'])->result_array();
			if(!$o_item_list)
				continue;
			
			$k++;
	?>
	<tr>
	<td><?=format_datetime(date('Y-m-d H:i:s',$o['time']))?></td>
	<td>
		<a href="<?=site_url("admin/trans/{$o['transid']}")?>" class="link"><?=$o['transid']?></a> <br /><br />
		Member ID: <a href="<?=site_url("admin/pnh_viewmember/{$o['userid']}")?>"><?=$o['pnh_member_id']?></a>
	</td>
	<td><?=round($o['amount'],2)?></td>
	<td><?=round($o['com'],2)?></td>
	<td style="padding:0px;">
		<table class="subdatagrid" cellpadding="0" cellspacing="0">
			<thead>
				<th>OID</th>
				<th>ITEM</th>
				<th>QTY</th>
				<th>MRP</th>
				<th>Amount</th>
				<th>Shipped</th>
			</thead>
			<tbody>
				<?php
					
					
					
					$trans_ttl_shipped = 0;
					$trans_ttl_cancelled = 0;
					$processed_oids = array();
					foreach($o_item_list as $o_item)
					{
						if(!isset($processed_oids[$o_item['id']]))
							$processed_oids[$o_item['id']] = 1;
						else
							continue;
						
						$ord_status_color = '';
						$is_shipped = 0;
						$is_cancelled = ($o_item['status']==3)?1:0;
						if($is_cancelled)
						{
							$trans_ttl_cancelled += 1;
							$ord_status_color = 'cancelled_ord';
						}else
						{
							$is_shipped = ($o_item['shipped'])?1:0;;
							if($o_item['shipped'] && $o_item['invoice_status'])
							{
								$trans_ttl_shipped += 1;
								$ship_dets[$o_item['invoice_no']] = format_date($o_item['shipped_on']);
								$ord_status_color = 'shipped_ord';
							}else if($o_item['status'] == 0)
							{
								$ord_status_color = 'pending_ord';
							}
						}
				?>
					<tr class="<?php echo $ord_status_color;?> ">
						<td width="40"><?php echo $o_item['id'] ?></td>
						<td><?php echo anchor('admin/pnh_deal/'.$o_item['itemid'],$o_item['name']) ?></td>
						<td width="20"><?php echo $o_item['quantity'] ?></td>
						<td width="40"><?php echo $o_item['i_orgprice'] ?></td>
						<td width="40"><?php echo round($o_item['i_orgprice']-($o_item['i_coup_discount']+$o_item['i_discount']),2) ?></td>
						<td width="40" align="center"><?php echo ($is_shipped&& $o_item['invoice_status'])?'Yes':'No' ?></td>
					</tr>	
				<?php 		
					}
					
					$trans_ttl_orders = count($processed_oids);
				?>
			</tbody>
		</table>
	</td>
	<td>
		<?php 
			if($trans_ttl_orders == $trans_ttl_cancelled)
			{
				echo "Cancelled";
			}else
			{
				if(($trans_ttl_orders-$trans_ttl_cancelled) == $trans_ttl_shipped)
				{
					echo "Shipped";
				}else if($trans_ttl_shipped)
				{
					echo "Partitally Shipped";
				}else {
					echo "UnShipped";
				}
			}
			echo '<div style="font-size:11px;margin-top:10px;color:green">';
			foreach($ship_dets as $s_invno =>$s_shipdate)
			{
				$status_mrp= $this->db->query("select round(sum(nlc*quantity)) as amt from king_invoice a join king_orders b on a.order_id = b.id where a.invoice_no = '".$s_invno."' ")->row()->amt;
				
				echo ' <div style="margin:3px 0px"><a target="_blank" href="'.site_url('admin/invoice/'.$s_invno).'">'.$s_invno.'-'.$s_shipdate.'</a> - Rs.'.$status_mrp.' </div>';
			}
			echo '</div>';
		?>
	</td>
	<td><?=$o['actiontime']==0?"na":format_datetime(date('Y-m-d H:i:s',$o['actiontime']))?></td>
	</tr>
	<?php }?>
	
	
	</tbody>
	</table>
	 <?php }?>
	 
	 <script>
	 	$('#ttl_orders_listed b').html(<?php echo $k;?>);
	 </script>
