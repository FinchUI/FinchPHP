/**
 * 链接管理中心 - 拖拽排序 & 搜索
 */
(function () {
    'use strict';

    // ── 拖拽排序 ──
    var sortForm = document.getElementById('fp-link-sort-form');
    var orderInput = document.getElementById('fp-link-order');
    var table = document.getElementById('fp-link-table');

    if (sortForm && orderInput && table) {
        var dragRow = null;

        table.addEventListener('dragstart', function (e) {
            var row = e.target.closest ? e.target.closest('.fp-link-row') : null;
            if (!row) return;
            dragRow = row;
            e.dataTransfer.effectAllowed = 'move';
            row.style.opacity = '0.4';
        });

        table.addEventListener('dragend', function (e) {
            if (dragRow) {
                dragRow.style.opacity = '';
                dragRow = null;
            }
        });

        table.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });

        table.addEventListener('drop', function (e) {
            e.preventDefault();
            if (!dragRow) return;
            var target = e.target.closest ? e.target.closest('.fp-link-row') : null;
            if (!target || target === dragRow) return;

            // Don't allow dropping parent onto its own child
            var parentId = dragRow.getAttribute('data-parent');
            if (parentId && parentId === target.getAttribute('data-id')) return;

            var tbody = table.querySelector('tbody') || table;
            var allRows = Array.from(tbody.querySelectorAll('.fp-link-row'));
            var dragIdx = allRows.indexOf(dragRow);
            var targetIdx = allRows.indexOf(target);

            if (dragIdx < targetIdx) {
                target.after(dragRow);
            } else {
                target.before(dragRow);
            }

            updateOrderValue();
        });

        function updateOrderValue() {
            var rows = table.querySelectorAll('.fp-link-row');
            var order = [];
            var currentParent = null;

            rows.forEach(function (row) {
                var id = parseInt(row.getAttribute('data-id'), 10);
                var parentId = row.getAttribute('data-parent');

                if (parentId) {
                    // This is a child item
                    if (order.length > 0) {
                        var lastItem = order[order.length - 1];
                        if (lastItem.id === parseInt(parentId, 10)) {
                            if (!lastItem.children) lastItem.children = [];
                            lastItem.children.push({ id: id });
                            return;
                        }
                    }
                    // Orphan child, find parent
                    for (var i = 0; i < order.length; i++) {
                        if (order[i].id === parseInt(parentId, 10)) {
                            if (!order[i].children) order[i].children = [];
                            order[i].children.push({ id: id });
                            return;
                        }
                    }
                } else {
                    order.push({ id: id });
                }
            });

            orderInput.value = JSON.stringify(order);
        }

        // Initialize order value
        updateOrderValue();
    }

    // ── 搜索 ──
    var searchBtn = document.getElementById('fp-link-search-btn');
    var searchType = document.getElementById('fp-link-search-type');
    var searchQ = document.getElementById('fp-link-search-q');
    var searchResults = document.getElementById('fp-link-search-results');

    if (searchBtn && searchType && searchQ && searchResults) {
        searchBtn.addEventListener('click', doSearch);
        searchQ.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                doSearch();
            }
        });

        function doSearch() {
            var type = searchType.value;
            var q = searchQ.value.trim();
            var params = '?type=' + encodeURIComponent(type) + '&q=' + encodeURIComponent(q) + '&limit=10';
            var url = '/admin/links/search' + params;

            // Also try active mode
            fetch(url, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (items) {
                    if (!items || items.length === 0) {
                        searchResults.innerHTML = '<p class="muted">没有找到结果</p>';
                        return;
                    }

                    var html = '<div class="fp-link-search-list">';
                    items.forEach(function (item) {
                        html += '<div class="fp-link-search-item">'
                            + '<span>' + escapeHtml(item.title) + '</span>'
                            + '<span class="muted" style="font-size:12px">' + escapeHtml(item.url) + '</span>'
                            + '<a href="/admin/links/create?type=' + encodeURIComponent(type)
                            + '&ref_id=' + item.id + '" class="button secondary" style="font-size:12px;padding:2px 8px">添加</a>'
                            + '</div>';
                    });
                    html += '</div>';
                    searchResults.innerHTML = html;
                })
                .catch(function () {
                    searchResults.innerHTML = '<p class="fp-error-text">搜索失败</p>';
                });
        }
    }

    function escapeHtml(s) {
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }
})();
