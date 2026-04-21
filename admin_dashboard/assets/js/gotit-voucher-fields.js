(function (window, document) {
    'use strict';

    var config = window.gameBscGotItVoucherFields || {};
    if (!config.ajaxUrl || !config.nonce || !config.action) {
        return;
    }

    var productsCache = null;
    var productDetailsCache = {};
    var isFetching = false;

    function query(selector, root) {
        return (root || document).querySelector(selector);
    }

    function createElement(tag, props) {
        var element = document.createElement(tag);
        if (!props) {
            return element;
        }

        Object.keys(props).forEach(function (key) {
            if (key === 'text') {
                element.textContent = props[key];
                return;
            }
            if (key === 'html') {
                element.innerHTML = props[key];
                return;
            }
            if (key === 'style') {
                Object.assign(element.style, props[key]);
                return;
            }
            element[key] = props[key];
        });

        return element;
    }

    function getField(fieldName) {
        return query('.acf-field[data-name="' + fieldName + '"]');
    }

    function getInput(field) {
        if (!field) {
            return null;
        }
        return query('input[type="number"], input[type="text"]', field);
    }

    function getAnyInput(field) {
        if (!field) {
            return null;
        }
        return query('input[type="text"], input[type="number"], input[type="url"], textarea', field);
    }

    function setFieldValue(fieldName, value) {
        var field = getField(fieldName);
        var input = getAnyInput(field);
        if (!input) {
            return;
        }

        input.value = value || '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function setGroupSubFieldValue(groupFieldName, subFieldName, value) {
        var groupField = getField(groupFieldName);
        if (!groupField) {
            return;
        }

        var subField = query('.acf-field[data-name="' + subFieldName + '"]', groupField);
        var input = getAnyInput(subField);
        if (!input) {
            return;
        }

        input.value = value || '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function setPostTitle(value) {
        var titleInput = query('#title');
        if (!titleInput) {
            return;
        }

        titleInput.value = value || '';
        titleInput.dispatchEvent(new Event('input', { bubbles: true }));
        titleInput.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function setPostContent(value) {
        var contentInput = query('#content');
        if (!contentInput) {
            return;
        }

        contentInput.value = value || '';
        contentInput.dispatchEvent(new Event('input', { bubbles: true }));
        contentInput.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function toPlainText(value) {
        if (!value) {
            return '';
        }

        var temp = document.createElement('div');
        temp.innerHTML = String(value);
        return (temp.textContent || temp.innerText || '').trim();
    }

    function pushStoreName(namesMap, rawName) {
        var normalized = toPlainText(rawName || '').replace(/\s+/g, ' ').trim();
        if (!normalized) {
            return;
        }
        namesMap[normalized] = true;
    }

    function collectStoreNamesFromNode(node, namesMap, depth) {
        if (!node || depth > 5) {
            return;
        }

        if (Array.isArray(node)) {
            node.forEach(function (item) {
                collectStoreNamesFromNode(item, namesMap, depth + 1);
            });
            return;
        }

        if (typeof node !== 'object') {
            return;
        }

        ['storeName', 'branchName', 'shopName', 'outletName', 'displayName', 'name'].forEach(function (key) {
            if (typeof node[key] === 'string' || typeof node[key] === 'number') {
                pushStoreName(namesMap, node[key]);
            }
        });

        Object.keys(node).forEach(function (key) {
            var value = node[key];
            if (!value || typeof value !== 'object') {
                return;
            }

            var lower = String(key).toLowerCase();
            var isStoreKey = lower.indexOf('store') !== -1
                || lower.indexOf('branch') !== -1
                || lower.indexOf('shop') !== -1
                || lower.indexOf('outlet') !== -1
                || lower === 'items'
                || lower === 'list'
                || lower === 'data';

            if (isStoreKey) {
                collectStoreNamesFromNode(value, namesMap, depth + 1);
            }
        });
    }

    function collectStoreNamesFromText(value, namesMap) {
        if (value === null || value === undefined) {
            return;
        }

        String(value).split(/[\r\n,;|]+/).forEach(function (part) {
            pushStoreName(namesMap, part);
        });
    }

    function getApplicableStoreNames(product) {
        var namesMap = {};
        var raw = (product && product.raw && typeof product.raw === 'object') ? product.raw : null;

        function scanStoreNodes(source) {
            if (!source || typeof source !== 'object') {
                return;
            }

            Object.keys(source).forEach(function (key) {
                var value = source[key];
                if (!value || typeof value !== 'object') {
                    return;
                }

                var lower = String(key).toLowerCase();
                if (lower.indexOf('store') !== -1
                    || lower.indexOf('branch') !== -1
                    || lower.indexOf('shop') !== -1
                    || lower.indexOf('outlet') !== -1) {
                    collectStoreNamesFromNode(value, namesMap, 0);
                }
            });
        }

        if (raw) {
            scanStoreNodes(raw);
            if (raw.data && typeof raw.data === 'object') {
                scanStoreNodes(raw.data);
            }
        }

        var extraFields = Array.isArray(product && product.extraFields) ? product.extraFields : [];
        extraFields.forEach(function (field) {
            if (!field || typeof field !== 'object') {
                return;
            }

            var fieldKey = String(field.key || field.name || '').toLowerCase();
            if (fieldKey.indexOf('store') === -1
                && fieldKey.indexOf('branch') === -1
                && fieldKey.indexOf('shop') === -1
                && fieldKey.indexOf('outlet') === -1) {
                return;
            }

            collectStoreNamesFromNode(field, namesMap, 0);
            ['value', 'values', 'content', 'label'].forEach(function (valueKey) {
                if (Object.prototype.hasOwnProperty.call(field, valueKey)) {
                    collectStoreNamesFromText(field[valueKey], namesMap);
                }
            });
        });

        return Object.keys(namesMap);
    }

    function setVoucherTypeThirdParty() {
        var radio = query('.acf-field[data-name="voucher_type"] input[type="radio"][value="THIRD_PARTY"]');
        if (!radio) {
            return;
        }

        if (!radio.checked) {
            radio.checked = true;
            radio.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function findPriceById(product, priceId) {
        if (!product || !Array.isArray(product.prices) || !priceId) {
            return null;
        }

        var target = String(priceId);
        for (var index = 0; index < product.prices.length; index++) {
            if (String(product.prices[index].productPriceId) === target) {
                return product.prices[index];
            }
        }

        return null;
    }

    function applyProductToVoucherFields(product, selectedPrice) {
        if (!product) {
            return;
        }

        var brand = product.brandInfo || {};
        var displayName = toPlainText(product.productName || '');
        var shortDescription = toPlainText(product.shortDescription || '');
        var longDescription = toPlainText(product.description || '');
        var serviceGuide = toPlainText(product.serviceGuide || '');
        var terms = toPlainText(product.terms || '');
        var applicableStores = getApplicableStoreNames(product);

        setVoucherTypeThirdParty();
        setPostTitle(displayName);
        setPostContent(longDescription || shortDescription);

        setFieldValue('voucher_display_name', displayName);
        setFieldValue('voucher_brand_name', toPlainText(brand.name || ''));
        setFieldValue('voucher_link_url', product.link || '');
        setFieldValue('voucher_image_url', product.image || '');
        setFieldValue('voucher_short_description', shortDescription);
        setFieldValue('voucher_long_description', longDescription);
        setFieldValue('voucher_service_guide', serviceGuide);
        setFieldValue('voucher_terms', terms);
        setFieldValue('voucher_applicable_stores', applicableStores.join('\n'));

        setFieldValue('voucher_selected_value', selectedPrice && selectedPrice.label ? toPlainText(selectedPrice.label) : '');

        setGroupSubFieldValue('partner', 'name', toPlainText(brand.name || displayName));
        if (product.link) {
            setGroupSubFieldValue('partner', 'url', product.link);
        }
    }

    function ensurePickerUI() {
        var productField = getField('gotit_product_id');
        var priceField = getField('gotit_product_price_id');
        var productInput = getInput(productField);
        var priceInput = getInput(priceField);

        if (!productField || !priceField || !productInput || !priceInput) {
            return;
        }

        if (!query('.game-bsc-gotit-picker', productField)) {
            renderProductPicker(productField, productInput, priceField, priceInput);
        }
    }

    function renderProductPicker(productField, productInput, priceField, priceInput) {
        var productWrapper = query('.acf-input', productField) || productField;
        var priceWrapper = query('.acf-input', priceField) || priceField;

        var pickerBox = createElement('div', {
            className: 'game-bsc-gotit-picker',
            style: {
                marginBottom: '12px',
                padding: '12px',
                border: '1px solid #dcdcde',
                borderRadius: '6px',
                background: '#fff'
            }
        });

        var title = createElement('div', {
            text: (config.messages && config.messages.pickerTitle) || 'Chọn nhanh từ API Got It',
            style: {
                fontWeight: '600',
                marginBottom: '6px'
            }
        });

        var hint = createElement('div', {
            text: (config.messages && config.messages.pickerHint) || 'Chọn sản phẩm và mệnh giá, hệ thống sẽ tự điền vào 2 ô bên dưới.',
            style: {
                color: '#646970',
                fontSize: '12px',
                marginBottom: '10px'
            }
        });

        var buttonRow = createElement('div', {
            style: {
                display: 'flex',
                gap: '8px',
                alignItems: 'center',
                marginBottom: '10px'
            }
        });

        var loadButton = createElement('button', {
            type: 'button',
            className: 'button button-secondary',
            text: 'Tải sản phẩm từ Got It'
        });

        var status = createElement('span', {
            style: {
                fontSize: '12px',
                color: '#646970'
            }
        });

        var productSelect = createElement('select', {
            className: 'widefat',
            style: {
                marginBottom: '8px',
                display: 'none'
            }
        });

        var priceSelect = createElement('select', {
            className: 'widefat',
            style: {
                marginBottom: '8px',
                display: 'none'
            }
        });

        var detailsBox = createElement('div', {
            className: 'game-bsc-gotit-details',
            style: {
                display: 'none',
                marginTop: '8px',
                padding: '10px',
                border: '1px solid #dcdcde',
                borderRadius: '6px',
                background: '#f9f9f9',
                fontSize: '12px'
            }
        });

        buttonRow.appendChild(loadButton);
        buttonRow.appendChild(status);
        pickerBox.appendChild(title);
        pickerBox.appendChild(hint);
        pickerBox.appendChild(buttonRow);
        pickerBox.appendChild(productSelect);
        pickerBox.appendChild(detailsBox);
        productWrapper.insertBefore(pickerBox, productWrapper.firstChild);
        priceWrapper.insertBefore(priceSelect, priceWrapper.firstChild);

        productInput.placeholder = 'Tự điền từ select box hoặc nhập tay';
        priceInput.placeholder = 'Tự điền từ select box hoặc nhập tay';

        loadButton.addEventListener('click', function () {
            fetchProducts(status, productSelect, priceSelect, productInput, priceInput, detailsBox);
        });

        productSelect.addEventListener('change', function () {
            var selectedProduct = findProductById(productSelect.value);
            if (!selectedProduct) {
                priceSelect.style.display = 'none';
                renderProductDetails(null, detailsBox);
                return;
            }

            productInput.value = selectedProduct.productId;
            priceInput.value = '';
            renderProductDetails(selectedProduct, detailsBox);
            applyProductToVoucherFields(selectedProduct, null);

            if (selectedProduct.prices && selectedProduct.prices.length > 0) {
                renderPriceOptions(selectedProduct, priceSelect, priceInput.value);
                priceSelect.style.display = '';
                return;
            }

            status.textContent = 'Đang tải mệnh giá cho sản phẩm ' + selectedProduct.productId + '...';
            loadProductDetails(selectedProduct.productId).then(function (detailedProduct) {
                if (!detailedProduct || !detailedProduct.prices || detailedProduct.prices.length === 0) {
                    priceSelect.innerHTML = '<option value="">Không tìm thấy mệnh giá</option>';
                    priceSelect.style.display = '';
                    status.textContent = 'Không có lựa chọn Product Price ID cho sản phẩm này.';
                    return;
                }

                updateCachedProduct(detailedProduct);
                renderPriceOptions(detailedProduct, priceSelect, priceInput.value);
                priceSelect.style.display = '';
                renderProductDetails(detailedProduct, detailsBox);
                applyProductToVoucherFields(detailedProduct, null);
                status.textContent = 'Đã tải mệnh giá cho sản phẩm ' + detailedProduct.productId + '.';
            }).catch(function () {
                priceSelect.innerHTML = '<option value="">Không tải được mệnh giá</option>';
                priceSelect.style.display = '';
                status.textContent = 'Lỗi khi tải Product Price ID.';
            });
        });

        priceSelect.addEventListener('change', function () {
            priceInput.value = priceSelect.value || '';
            var selectedProduct = findProductById(productSelect.value);
            var selectedPrice = findPriceById(selectedProduct, priceSelect.value);
            applyProductToVoucherFields(selectedProduct, selectedPrice);
        });

        if (productInput.value && priceInput.value) {
            fetchProducts(status, productSelect, priceSelect, productInput, priceInput, detailsBox, true);
        }

        fetchProducts(status, productSelect, priceSelect, productInput, priceInput, detailsBox, true);
    }

    function fetchProducts(status, productSelect, priceSelect, productInput, priceInput, detailsBox, silent) {
        if (isFetching) {
            return;
        }

        if (productsCache) {
            hydrateSelectors(productSelect, priceSelect, productInput, priceInput, detailsBox);
            if (!silent) {
                status.textContent = 'Đã tải xong danh sách sản phẩm.';
            }
            return;
        }

        isFetching = true;
        status.textContent = (config.messages && config.messages.loading) || 'Đang tải...';

        var body = new URLSearchParams();
        body.append('action', config.action);
        body.append('nonce', config.nonce);
        if (productInput && productInput.value) {
            body.append('ids', String(productInput.value).trim());
            body.append('storeListPage', '1');
            body.append('storeListPageSize', '10');
            body.append('isExcludeStoreListInfo', 'false');
        }

        fetch(config.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            if (!payload.success || !payload.data || !Array.isArray(payload.data.products)) {
                var serverMessage = (payload && payload.data && payload.data.message) ? payload.data.message : '';
                var serverHint = (payload && payload.data && payload.data.hint) ? payload.data.hint : '';
                throw new Error((serverMessage || 'invalid_products_response') + (serverHint ? ' - ' + serverHint : ''));
            }

            productsCache = payload.data.products;
            hydrateSelectors(productSelect, priceSelect, productInput, priceInput, detailsBox);
            status.textContent = 'Đã tải ' + productsCache.length + ' sản phẩm.';
        }).catch(function (error) {
            if (error && error.message && error.message !== 'invalid_products_response') {
                status.textContent = error.message;
                return;
            }
            status.textContent = (config.messages && config.messages.loadFailed) || 'Không tải được danh sách sản phẩm.';
        }).finally(function () {
            isFetching = false;
        });
    }

    function loadProductDetails(productId) {
        if (!productId) {
            return Promise.resolve(null);
        }

        var cacheKey = String(productId);
        if (productDetailsCache[cacheKey]) {
            return Promise.resolve(productDetailsCache[cacheKey]);
        }

        var body = new URLSearchParams();
        body.append('action', config.action);
        body.append('nonce', config.nonce);
        body.append('ids', cacheKey);
        body.append('storeListPage', '1');
        body.append('storeListPageSize', '10');
        body.append('isExcludeStoreListInfo', 'false');

        return fetch(config.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            if (!payload.success || !payload.data || !Array.isArray(payload.data.products) || payload.data.products.length === 0) {
                return null;
            }

            productDetailsCache[cacheKey] = payload.data.products[0];
            return productDetailsCache[cacheKey];
        });
    }

    function hydrateSelectors(productSelect, priceSelect, productInput, priceInput, detailsBox) {
        var productOptions = ['<option value="">' + (((config.messages && config.messages.selectProduct) || '-- Chọn sản phẩm Got It --')) + '</option>'];
        productsCache.forEach(function (product) {
            productOptions.push('<option value="' + escapeHtml(String(product.productId)) + '">' + escapeHtml(String(product.productId) + ' - ' + product.productName) + '</option>');
        });
        productSelect.innerHTML = productOptions.join('');
        productSelect.style.display = '';

        if (productInput.value) {
            productSelect.value = String(productInput.value);
            var selectedProduct = findProductById(productInput.value);
            if (selectedProduct) {
                renderPriceOptions(selectedProduct, priceSelect, priceInput.value);
                priceSelect.style.display = '';
                if (priceInput.value) {
                    priceSelect.value = String(priceInput.value);
                }
                renderProductDetails(selectedProduct, detailsBox);
            }
        }
    }

    function renderProductDetails(product, detailsBox) {
        if (!detailsBox) {
            return;
        }

        if (!product) {
            detailsBox.style.display = 'none';
            detailsBox.innerHTML = '';
            return;
        }

        var images = [];
        if (product.image) {
            images.push(product.image);
        }
        (product.additionalImages || []).forEach(function (img) {
            if (img && images.indexOf(img) === -1) {
                images.push(img);
            }
        });

        var prices = (product.prices || []).map(function (p) {
            return (p.label || p.productPriceId || '');
        }).join('\n');
        var applicableStores = getApplicableStoreNames(product);

        var brand = product.brandInfo || {};

        var html = '';
        html += '<div style="font-weight:600;margin-bottom:6px;">Thông tin voucher</div>';
        html += '<div><b>Tên voucher:</b> ' + escapeHtml(String(product.productName || '')) + '</div>';
        html += '<div><b>Thương hiệu:</b> ' + escapeHtml(String(brand.name || '')) + '</div>';
        if (product.link) {
            html += '<div><b>Đường dẫn:</b> <a href="' + escapeHtml(String(product.link)) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(String(product.link)) + '</a></div>';
        }
        html += '<div style="margin-top:6px;"><b>Các mệnh giá:</b><pre style="white-space:pre-wrap;background:#fff;border:1px solid #ddd;padding:6px;max-height:140px;overflow:auto;">' + escapeHtml(prices || '(chưa có)') + '</pre></div>';

        if (images.length > 0) {
            html += '<div style="margin-top:6px;"><b>Hình ảnh:</b><br>';
            images.forEach(function (img) {
                html += '<a href="' + escapeHtml(String(img)) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(String(img)) + '</a><br>';
            });
            html += '</div>';
        }

        html += '<div><b>Cửa hàng áp dụng:</b><pre style="white-space:pre-wrap;background:#fff;border:1px solid #ddd;padding:6px;max-height:120px;overflow:auto;">' + escapeHtml(applicableStores.length ? applicableStores.join('\n') : '(chưa có dữ liệu)') + '</pre></div>';
        html += '<div style="margin-top:6px;"><b>Mô tả ngắn:</b><pre style="white-space:pre-wrap;background:#fff;border:1px solid #ddd;padding:6px;max-height:120px;overflow:auto;">' + escapeHtml(String(product.shortDescription || '')) + '</pre></div>';
        html += '<div><b>Mô tả chi tiết:</b><pre style="white-space:pre-wrap;background:#fff;border:1px solid #ddd;padding:6px;max-height:120px;overflow:auto;">' + escapeHtml(String(product.description || '')) + '</pre></div>';
        html += '<div><b>Hướng dẫn sử dụng:</b><pre style="white-space:pre-wrap;background:#fff;border:1px solid #ddd;padding:6px;max-height:120px;overflow:auto;">' + escapeHtml(String(product.serviceGuide || '')) + '</pre></div>';
        html += '<div><b>Điều kiện sử dụng:</b><pre style="white-space:pre-wrap;background:#fff;border:1px solid #ddd;padding:6px;max-height:160px;overflow:auto;">' + escapeHtml(String(product.terms || '')) + '</pre></div>';

        detailsBox.innerHTML = html;
        detailsBox.style.display = '';
    }

    function renderPriceOptions(product, priceSelect, selectedValue) {
        var priceOptions = ['<option value="">' + (((config.messages && config.messages.selectPrice) || '-- Chọn mệnh giá Got It --')) + '</option>'];
        (product.prices || []).forEach(function (price) {
            priceOptions.push('<option value="' + escapeHtml(String(price.productPriceId)) + '">' + escapeHtml(price.label || String(price.productPriceId)) + '</option>');
        });
        priceSelect.innerHTML = priceOptions.join('');
        if (selectedValue) {
            priceSelect.value = String(selectedValue);
        }
    }

    function findProductById(productId) {
        if (!productsCache) {
            return null;
        }
        var target = String(productId);
        for (var index = 0; index < productsCache.length; index++) {
            if (String(productsCache[index].productId) === target) {
                return productsCache[index];
            }
        }
        return null;
    }

    function updateCachedProduct(product) {
        if (!productsCache || !product || !product.productId) {
            return;
        }

        var target = String(product.productId);
        for (var index = 0; index < productsCache.length; index++) {
            if (String(productsCache[index].productId) === target) {
                productsCache[index] = product;
                return;
            }
        }

        productsCache.push(product);
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    if (window.acf && typeof window.acf.addAction === 'function') {
        window.acf.addAction('ready append', ensurePickerUI);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ensurePickerUI);
    } else {
        ensurePickerUI();
    }
})(window, document);