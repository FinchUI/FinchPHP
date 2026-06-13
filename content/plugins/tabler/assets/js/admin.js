document.addEventListener('DOMContentLoaded', function () {
  var tables = document.querySelectorAll('table');
  tables.forEach(function (table) {
    var parent = table.parentElement;
    if (!parent) {
      return;
    }

    if (parent.classList.contains('fp-table-scroll')) {
      return;
    }

    var wrapper = document.createElement('div');
    wrapper.className = 'fp-table-scroll';
    parent.insertBefore(wrapper, table);
    wrapper.appendChild(table);
  });
});
