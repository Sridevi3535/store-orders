<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Store Billing — New Order</title>
<style>
  * { box-sizing: border-box; }
  body { margin: 0; font-family: Arial, sans-serif; background: #f5f6fa; color: #222; }
  header { background: #1f2a3c; color: #fff; padding: 14px 24px; font-weight: bold; }
  .wrap { display: flex; gap: 24px; padding: 24px; flex-wrap: wrap; }
  .main { flex: 1; min-width: 480px; }
  .side { width: 280px; }
  .card { background: #fff; border: 1px solid #d6dbe4; border-radius: 6px; padding: 16px; margin-bottom: 16px; }
  label { font-size: 12px; font-weight: bold; display: block; margin: 8px 0 4px; }
  input, select { width: 100%; padding: 8px; border: 1px solid #b9c1d0; border-radius: 4px; }
  .row { display: flex; gap: 12px; }
  .row > div { flex: 1; }
  table { width: 100%; border-collapse: collapse; }
  th, td { padding: 6px; border-bottom: 1px solid #e5e8ee; text-align: left; }
  th { background: #e8ecf3; font-size: 12px; }
  button { padding: 9px 16px; border: 0; border-radius: 4px; color: #fff; cursor: pointer; background: #2f6fb5; }
  button.green { background: #1f8a3b; }
  button.red { background: #c0392b; padding: 5px 10px; }
  .alert { background: #fff8e6; border-color: #e08a00; }
  .alert h4 { margin: 0 0 8px; color: #a35c00; }
  .tot div { display: flex; justify-content: space-between; padding: 3px 0; }
  .err { color: #c0392b; font-size: 13px; margin-top: 8px; }
  .hint { font-size: 12px; color: #555; }
</style>
</head>
<body>
<header>Store Billing — New Order</header>

<div class="wrap">
  <div class="main">
    <div class="card">
      <strong>Customer</strong>
      <div class="row">
        <div><label>Email</label><input id="email" type="email" placeholder="e.g. thomas&#64;example.com"></div>
        <div><label>Name</label><input id="name" placeholder="auto-filled if email exists"></div>
      </div>
    </div>

    <div class="card">
      <strong>Products</strong>
      <table>
        <thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Line Total (incl. tax)</th><th></th></tr></thead>
        <tbody id="lines"></tbody>
      </table>
      <p><button id="add" type="button">+ Add Product</button></p>
    </div>

    <div class="card" style="max-width:420px">
      <strong>Payment</strong>
      <div class="tot">
        <div><span>Subtotal</span><b id="sub">₹0.00</b></div>
        <div><span>Tax</span><b id="tax">₹0.00</b></div>
        <div><span>Grand Total</span><b id="grand">₹0.00</b></div>
      </div>
      <label>Amount Given by Customer</label>
      <input id="given" type="number" min="0" step="0.01" placeholder="₹250">
      <div class="tot"><div><span>Balance to Return</span><b id="balance">—</b></div></div>
      <div class="hint" id="notes"></div>
      <p><button id="generate" type="button" class="green">Generate Bill</button></p>
      <div id="msg" class="err"></div>
    </div>

    <div id="bill"></div>
  </div>

  <div class="side">
    <div class="card alert">
      <h4>⚠ Low Stock Alert</h4>
      <ul id="low" style="margin:0;padding-left:18px"></ul>
    </div>
  </div>
</div>

<script>
const $ = id => document.getElementById(id);
const money = n => '₹' + Number(n).toFixed(2);
const round2 = n => Math.round((n + Number.EPSILON) * 100) / 100;
const esc = s => String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
let products = [];

async function api(url, options = {}) {
  const res = await fetch(url, {
    ...options,
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
  });
  let data = {};
  try { data = await res.json(); } catch (e) {}
  return { ok: res.ok, status: res.status, data };
}

async function loadProducts() {
  products = (await api('/api/products')).data.data || [];
  const low = (await api('/api/products/low-stock')).data.data || [];
  $('low').innerHTML = low.length
    ? low.map(p => `<li>${esc(p.name)} — ${p.stock} units left</li>`).join('')
    : '<li>All products well stocked</li>';
}

function addRow() {
  const tr = document.createElement('tr');
  const options = products.map(p => `<option value="${p.id}">${esc(p.name)} (${p.stock} in stock)</option>`).join('');
  tr.innerHTML = `
    <td><select class="p">${options}</select></td>
    <td><input class="q" type="number" min="1" value="1" style="width:80px"></td>
    <td class="pr"></td>
    <td class="lt"></td>
    <td><button class="red" type="button">✕</button></td>`;
  tr.querySelector('.red').onclick = () => { tr.remove(); recalc(); };
  tr.querySelectorAll('select,input').forEach(el => el.oninput = recalc);
  $('lines').appendChild(tr);
  recalc();
}

function collect() {
  return [...$('lines').children].map(tr => ({
    product_id: Number(tr.querySelector('.p').value),
    quantity: Number(tr.querySelector('.q').value),
  }));
}

function recalc() {
  let sub = 0, tax = 0;
  [...$('lines').children].forEach(tr => {
    const p = products.find(x => x.id == tr.querySelector('.p').value);
    const q = Number(tr.querySelector('.q').value) || 0;
    if (!p) return;
    const ls = round2(parseFloat(p.price) * q);
    const lt = round2(ls * parseFloat(p.tax_percent) / 100);
    tr.querySelector('.pr').textContent = money(p.price);
    tr.querySelector('.lt').textContent = money(ls + lt);
    sub += ls; tax += lt;
  });
  sub = round2(sub); tax = round2(tax);
  const grand = round2(sub + tax);
  $('sub').textContent = money(sub);
  $('tax').textContent = money(tax);
  $('grand').textContent = money(grand);

  const given = parseFloat($('given').value);
  if (isNaN(given)) { $('balance').textContent = '—'; $('notes').textContent = ''; return; }
  if (given < grand) { $('balance').textContent = '—'; $('notes').textContent = 'Amount given is less than the grand total.'; return; }

  const bal = round2(given - grand);
  $('balance').textContent = money(bal);
  let rest = Math.floor(bal);
  const parts = [];
  for (const d of [500, 200, 100, 50, 20, 10, 5, 2, 1]) {
    const c = Math.floor(rest / d);
    if (c) { parts.push(`${c}×₹${d}`); rest -= c * d; }
  }
  $('notes').textContent = parts.length ? 'Notes/coins: ' + parts.join(' + ') : '';
}

function showBill(o) {
  $('bill').innerHTML = `
    <div class="card">
      <strong>Bill #${o.id}</strong> — ${esc(o.customer.name)} (${esc(o.customer.email)})
      <table>
        <tr><th>Product</th><th>Qty</th><th>Price</th><th>Tax %</th><th>Total</th></tr>
        ${o.items.map(i => `<tr><td>${esc(i.product_name)}</td><td>${i.quantity}</td><td>${money(i.unit_price)}</td><td>${i.tax_percent}%</td><td>${money(i.line_total)}</td></tr>`).join('')}
      </table>
      <p>Subtotal: <b>${money(o.subtotal)}</b> | Tax: <b>${money(o.tax_total)}</b> | Grand Total: <b>${money(o.grand_total)}</b></p>
      ${o.amount_given !== null ? `<p>Given: ${money(o.amount_given)} | Balance: <b>${money(o.balance_returned)}</b></p>` : ''}
      <p class="hint">Confirmation email queued (simulated, see storage/logs/laravel.log).</p>
    </div>`;
}

$('add').onclick = addRow;
$('given').oninput = recalc;

$('email').onblur = async () => {
  const email = $('email').value.trim();
  if (!email) return;
  const r = await api('/api/customers/lookup?email=' + encodeURIComponent(email));
  if (r.ok) $('name').value = r.data.data.name;
};

$('generate').onclick = async () => {
  $('msg').textContent = '';
  $('bill').innerHTML = '';
  const items = collect();
  if (!$('email').value.trim() || !items.length) {
    $('msg').textContent = 'Enter customer email and add at least one product.';
    return;
  }
  const body = {
    customer_email: $('email').value.trim(),
    customer_name: $('name').value.trim() || null,
    items,
  };
  if ($('given').value !== '') body.amount_given = Number($('given').value);

  const r = await api('/api/orders', { method: 'POST', body: JSON.stringify(body) });
  if (r.status === 201) {
    showBill(r.data.data);
    $('lines').innerHTML = '';
    $('given').value = '';
    await loadProducts();
    addRow();
  } else {
    const errs = r.data.errors ? Object.values(r.data.errors).flat().join(' ') : '';
    $('msg').textContent = (r.data.message || 'Something went wrong') + ' ' + errs;
  }
};

loadProducts().then(addRow);
</script>
</body>
</html>