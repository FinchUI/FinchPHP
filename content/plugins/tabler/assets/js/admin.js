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

  // Quill 编辑器初始化
  var quillEl = document.getElementById('fp-quill-editor');
  if (quillEl && typeof Quill !== 'undefined') {
    var quill = new Quill('#fp-quill-editor', {
      theme: 'snow',
      placeholder: '',
      modules: {
        toolbar: [
          [{ header: [1, 2, 3, 4, 5, 6, false] }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ color: [] }, { background: [] }],
          [{ list: 'ordered' }, { list: 'bullet' }],
          [{ align: [] }],
          ['blockquote', 'code-block'],
          ['link', 'image', 'video'],
          ['clean'],
        ],
      },
    });

    // 保存初始内容到 hidden input
    var hiddenInput = document.getElementById('fp-quill-content');
    if (hiddenInput) {
      hiddenInput.value = quill.root.innerHTML;
    }

    // 内容变化时同步到 hidden input
    quill.on('text-change', function () {
      if (hiddenInput) {
        hiddenInput.value = quill.root.innerHTML;
      }
    });

    // 表单提交前同步
    var form = quillEl.closest('form');
    if (form) {
      form.addEventListener('submit', function () {
        if (hiddenInput) {
          hiddenInput.value = quill.root.innerHTML;
        }
      });
    }
  }
});
