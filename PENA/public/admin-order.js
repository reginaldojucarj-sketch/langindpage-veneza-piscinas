(() => {
  const list = document.getElementById('post-order-list');
  if (!list) return;

  function refresh() {
    const items = [...list.children];
    items.forEach((item, index) => {
      item.querySelector('[data-move="up"]').disabled = index === 0;
      item.querySelector('[data-move="down"]').disabled = index === items.length - 1;
    });
  }

  list.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-move]');
    if (!button) return;
    const item = button.closest('li');
    if (button.dataset.move === 'up' && item.previousElementSibling) {
      list.insertBefore(item, item.previousElementSibling);
    } else if (button.dataset.move === 'down' && item.nextElementSibling) {
      list.insertBefore(item.nextElementSibling, item);
    }
    refresh();
    button.focus();
  });

  refresh();
})();
