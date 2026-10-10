<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
require_login();
$u=$_SESSION['user']; $r=$u['role'];

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
    $action=$_POST['action'];
    $ids=array_values(array_unique(array_map('intval', $_POST['order_ids'] ?? (isset($_POST['order_id'])?[$_POST['order_id']]:[]))));
    $ids=array_values(array_filter($ids, fn($id)=>$id>0));
    foreach($ids as $id) {
        if($action==='cancel' && $r==='customer') {
            $s=$conn->prepare("UPDATE orders SET status='Cancelled' WHERE id=? AND customer_id=? AND status='Pending'");
            $s->bind_param('ii',$id,$u['id']); $s->execute();
        } elseif(can_confirm($r) && in_array($action,['accept','reject'],true)) {
            $status=$action==='accept'?'Accepted':'Rejected';
            $s=$conn->prepare("UPDATE orders SET status=?,confirmed_by=? WHERE id=? AND status='Pending'");
            $s->bind_param('sii',$status,$u['id'],$id); $s->execute();
        } elseif(can_confirm($r) && $action==='processing') {
            $s=$conn->prepare("UPDATE orders SET status='Processing' WHERE id=? AND status='Accepted'");
            $s->bind_param('i',$id); $s->execute();
        } elseif(can_ship($r) && $action==='shipped') {
            $s=$conn->prepare("UPDATE orders SET status='Shipped',shipped_by=?,shipped_at=NOW() WHERE id=? AND status='Processing'");
            $s->bind_param('ii',$u['id'],$id); $s->execute();
        } elseif(can_ship($r) && $action==='paid') {
            // Staff can record money received for COD orders. Online payments should be confirmed by the payment gateway/admin workflow.
            $s=$conn->prepare("UPDATE payments SET status='Paid',transaction_date=NOW() WHERE order_id=? AND method='Cash on Delivery' AND status='Pending'");
            $s->bind_param('i',$id); $s->execute();
        } elseif(can_ship($r) && $action==='delivered') {
            // Delivery cannot be completed until payment is recorded as paid.
            $s=$conn->prepare("UPDATE orders o SET o.status='Delivered' WHERE o.id=? AND o.status='Shipped' AND EXISTS (SELECT 1 FROM payments p WHERE p.order_id=o.id AND p.status='Paid')");
            $s->bind_param('i',$id); $s->execute();
        }
    }
    $msg='Order update completed. If an action was unavailable for the current status or payment state, that order was left unchanged.';
    header('Location: orders.php?msg='.urlencode($msg)); exit;
}

$customerFilter=(int)($_GET['customer_id']??0); $statusFilter=$_GET['status']??''; $from=$_GET['from']??''; $to=$_GET['to']??'';
$where=[]; $types=''; $params=[];
if($r==='customer') { $where[]='o.customer_id=?'; $types.='i'; $params[]=$u['id']; }
elseif($customerFilter) { $where[]='o.customer_id=?'; $types.='i'; $params[]=$customerFilter; }
if($statusFilter!=='') { $where[]='o.status=?'; $types.='s'; $params[]=$statusFilter; }
if($from!=='') { $where[]='DATE(o.order_date)>=?'; $types.='s'; $params[]=$from; }
if($to!=='') { $where[]='DATE(o.order_date)<=?'; $types.='s'; $params[]=$to; }
$sql='SELECT o.*,u.name customer,u.email FROM orders o JOIN users u ON u.id=o.customer_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY o.id DESC';
$s=$conn->prepare($sql); if($types) $s->bind_param($types,...$params); $s->execute(); $rows=$s->get_result();
$customers=$conn->query("SELECT id,name,email FROM users WHERE role='customer' ORDER BY name");
include 'includes/header.php';
?>
<div class="card"><h2>Order Management</h2>
<?php if($r==='customer'): ?><a class="btn" href="products.php">Continue Shopping</a>
<?php else: ?><form method="get" class="grid"><div><label>Customer</label><select name="customer_id"><option value="0">All customers</option><?php while($c=$customers->fetch_assoc()): ?><option value="<?=$c['id']?>" <?=$customerFilter===(int)$c['id']?'selected':''?>><?=h($c['name'])?> (<?=h($c['email'])?>)</option><?php endwhile; ?></select></div><div><label>Status</label><select name="status"><option value="">All statuses</option><?php foreach(['Pending','Accepted','Rejected','Processing','Shipped','Delivered','Cancelled'] as $st): ?><option value="<?=$st?>" <?=$statusFilter===$st?'selected':''?>><?=$st?></option><?php endforeach; ?></select></div><div><label>From date</label><input type="date" name="from" value="<?=h($from)?>"></div><div><label>To date</label><input type="date" name="to" value="<?=h($to)?>"></div><div><button class="btn">Filter Orders</button> <a href="orders.php">Clear</a></div></form><?php endif; ?></div>
<form method="post"><div class="card"><div class="actions">
<?php if(can_confirm($r)): ?><button class="btn success" name="action" value="accept" onclick="return confirm('Accept selected pending orders?')">Accept Selected</button><button class="btn danger" name="action" value="reject" onclick="return confirm('Reject selected pending orders?')">Reject Selected</button><button class="btn" name="action" value="processing">Mark Processing</button><?php endif; ?>
<?php if(can_ship($r)): ?><button class="btn success" name="action" value="shipped" onclick="return confirm('Mark selected processing orders as shipped?')">Ship Selected</button><button class="btn" name="action" value="paid" onclick="return confirm('Confirm cash received for selected Cash on Delivery orders?')">Confirm COD Payment Received</button><button class="btn" name="action" value="delivered" onclick="return confirm('Mark selected paid orders as delivered? Unpaid orders will not be delivered.')">Confirm Delivered</button><?php endif; ?>
<?php if($r==='customer'): ?><button class="btn danger" name="action" value="cancel" onclick="return confirm('Cancel selected pending orders?')">Cancel Selected</button><?php endif; ?></div>
<p><small>Shipping staff: for Cash on Delivery, collect the amount from the customer and click <b>Confirm COD Payment Received</b> before confirming delivery. The payment status is updated in the order list and reports. Orders cannot be marked delivered until payment is Paid.</small></p>
<table><tr><th><input type="checkbox" onclick="document.querySelectorAll('.pick').forEach(x=>x.checked=this.checked)"></th><th>Order</th><th>Customer</th><th>Date</th><th>Status</th><th>Payment</th><th>Total</th><th>Shipping</th></tr>
<?php while($o=$rows->fetch_assoc()): $ps=$conn->prepare('SELECT method,status,amount FROM payments WHERE order_id=? ORDER BY id DESC LIMIT 1'); $ps->bind_param('i',$o['id']); $ps->execute(); $pay=$ps->get_result()->fetch_assoc(); ?>
<tr><td><input class="pick" type="checkbox" name="order_ids[]" value="<?=$o['id']?>"></td><td>#<?=$o['id']?></td><td><?=h($o['customer'])?><br><small><?=h($o['email'])?></small></td><td><?=h($o['order_date'])?></td><td><span class="badge"><?=h($o['status'])?></span></td><td><?=h($pay['method']??'-')?> / <b><?=h($pay['status']??'Unrecorded')?></b></td><td>₹<?=number_format((float)$o['total'],2)?></td><td><?=h($o['shipping_address'])?><?php if($o['shipped_at']): ?><br><small>Shipped <?=h($o['shipped_at'])?></small><?php endif; ?></td></tr>
<?php endwhile; ?></table></div></form>
<?php include 'includes/footer.php'; ?>
