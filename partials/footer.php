</div>

<script>
function openMenu() {
  document.getElementById('drawer').classList.add('open');
  document.getElementById('drawerOverlay').classList.add('show');
}

function closeMenu() {
  document.getElementById('drawer').classList.remove('open');
  document.getElementById('drawerOverlay').classList.remove('show');
}

function changeQty(button, amount) {
  const wrap = button.closest('.soh-control');
  if (!wrap) return;
  const input = wrap.querySelector("input[type='number']");
  if (!input) return;
  let value = parseInt(input.value || 0, 10);
  value = value + amount;
  if (value < 0) value = 0;
  input.value = value;
  input.dispatchEvent(new Event('input', { bubbles: true }));
}

function toggleSohEdit(open) {
  const form = document.getElementById('sohBulkForm');
  const read = document.getElementById('sohReadView');
  const btn = document.getElementById('sohEditBtn');
  if (!form) return;
  if (!open && form.querySelector('.soh-bulk-row.changed') && !confirm('Discard your SOH changes?')) return;
  if (!open) form.reset();
  form.querySelectorAll('.qty-input').forEach(sohRowChanged);
  form.style.display = open ? 'block' : 'none';
  if (read) read.style.display = open ? 'none' : 'block';
  if (btn) btn.style.display = open ? 'none' : '';
}

function sohRowChanged(input) {
  const row = input.closest('.soh-bulk-row');
  if (!row) return;
  const soh = Math.max(parseInt(input.value || 0, 10), 0);
  row.querySelector('.soh-bulk-po').textContent = Math.max(parseInt(row.dataset.mos, 10) - soh, 0);
  row.classList.toggle('changed', String(soh) !== input.dataset.orig);
  const count = document.querySelectorAll('#sohBulkForm .soh-bulk-row.changed').length;
  const label = document.getElementById('sohChangedCount');
  if (label) label.textContent = count ? count + ' changed' : 'No changes yet';
}

function openProductModal() {
  const modal = document.getElementById('productModal');
  const overlay = document.getElementById('productModalOverlay');
  if (modal) modal.classList.add('show');
  if (overlay) overlay.classList.add('show');
}

function closeProductModal() {
  const modal = document.getElementById('productModal');
  const overlay = document.getElementById('productModalOverlay');
  if (modal) modal.classList.remove('show');
  if (overlay) overlay.classList.remove('show');
}

function openUnitModal() {
  const modal = document.getElementById('unitModal');
  const overlay = document.getElementById('unitModalOverlay');
  if (modal) modal.classList.add('show');
  if (overlay) overlay.classList.add('show');
}

function closeUnitModal() {
  const modal = document.getElementById('unitModal');
  const overlay = document.getElementById('unitModalOverlay');
  if (modal) modal.classList.remove('show');
  if (overlay) overlay.classList.remove('show');
}

function updateDateTime() {
  const el = document.getElementById('liveDateTime');
  if (!el) return;
  const now = new Date();
  const date = now.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' });
  const time = now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
  el.textContent = date + ' · ' + time;
}

window.addEventListener('load', function () {
  updateDateTime();
  setInterval(updateDateTime, 60000);
  const loader = document.getElementById('appLoader');
  setTimeout(function () {
    if (loader) loader.classList.add('hide');
  }, 700);
});
</script>

</body>
</html>
