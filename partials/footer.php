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
