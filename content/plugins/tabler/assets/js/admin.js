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
        toolbar: {
          container: [
            [{ header: [1, 2, 3, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ color: [] }, { background: [] }],
            [{ list: 'ordered' }, { list: 'bullet' }],
            [{ align: [] }],
            ['blockquote', 'code-block'],
            ['link', 'image'],
            ['clean'],
            ['html-source'], // HTML 源码切换
          ],
          handlers: {
            'html-source': function () {
              toggleSourceMode();
            },
          },
        },
      },
    });

    // ====== HTML 源码切换 ======
    var sourceMode = false;
    var sourceTextarea = null;

    function ensureSourceTextarea() {
      if (sourceTextarea) {
        return sourceTextarea;
      }
      var ta = document.createElement('textarea');
      ta.id = 'fp-quill-source';
      ta.className = 'fp-quill-source-textarea';
      ta.spellcheck = false;
      // 插入到编辑器容器之后
      quillEl.parentNode.insertBefore(ta, quillEl.nextSibling);
      sourceTextarea = ta;
      return ta;
    }

    function toggleSourceMode() {
      var ta = ensureSourceTextarea();
      var hiddenInput = document.getElementById('fp-quill-content');

      if (!sourceMode) {
        // 切入源码模式
        ta.value = formatHtml(quill.root.innerHTML);
        quillEl.style.display = 'none';
        ta.style.display = '';
        ta.classList.add('fp-quill-source-active');
        sourceMode = true;
        // 更新按钮状态
        updateSourceButton(true);
      } else {
        // 切回可视化模式
        var html = ta.value;
        // 安全过滤后再设回编辑器
        quill.root.innerHTML = html;
        ta.style.display = 'none';
        ta.classList.remove('fp-quill-source-active');
        quillEl.style.display = '';
        sourceMode = false;
        // 同步 hidden input
        if (hiddenInput) {
          hiddenInput.value = quill.root.innerHTML;
        }
        updateSourceButton(false);
      }
    }

    function updateSourceButton(isActive) {
      var btns = document.querySelectorAll('.ql-html-source');
      btns.forEach(function (btn) {
        if (isActive) {
          btn.classList.add('ql-active');
        } else {
          btn.classList.remove('ql-active');
        }
      });
    }

    function formatHtml(html) {
      // 简单的 HTML 格式化缩进
      var formatted = '';
      var indent = '';
      // 将 > 和 < 之间的空白规范化
      html = html.replace(/>\s*</g, '><'); // 先压缩
      var tagPattern = /<(\/?)(\w+)[^>]*>/g;
      var lastIndex = 0;
      var result;
      var voidElements = ['br','hr','img','input','meta','link','area','base','col','embed','param','source','track','wbr'];

      while ((result = tagPattern.exec(html)) !== null) {
        var fullMatch = result[0];
        var pos = result.index;
        var isClosing = result[1] === '/';
        var tagName = result[2].toLowerCase();

        if (pos > lastIndex) {
          var text = html.substring(lastIndex, pos).trim();
          if (text) {
            formatted += indent + text + '\n';
          }
        }

        if (isClosing || voidElements.indexOf(tagName) !== -1) {
          if (isClosing && voidElements.indexOf(tagName) === -1) {
            indent = indent.substring(2);
          }
          formatted += indent + fullMatch + '\n';
        } else {
          formatted += indent + fullMatch + '\n';
          indent += '  ';
        }
        lastIndex = pos + fullMatch.length;
      }
      if (lastIndex < html.length) {
        var tail = html.substring(lastIndex).trim();
        if (tail) {
          formatted += indent + tail + '\n';
        }
      }
      return formatted.trim() || html;
    }

    // 保存初始内容到 hidden input
    var hiddenInput = document.getElementById('fp-quill-content');
    if (hiddenInput) {
      hiddenInput.value = quill.root.innerHTML;
    }

    // 内容变化时同步到 hidden input
    quill.on('text-change', function () {
      if (!sourceMode && hiddenInput) {
        hiddenInput.value = quill.root.innerHTML;
      }
    });

    // 表单提交前同步（含源码模式）
    var form = quillEl.closest('form');
    if (form) {
      form.addEventListener('submit', function () {
        if (sourceMode && sourceTextarea) {
          // 从源码模式切回时同步
          quill.root.innerHTML = sourceTextarea.value;
        }
        if (hiddenInput) {
          hiddenInput.value = quill.root.innerHTML;
        }
      });
    }
  }
});
