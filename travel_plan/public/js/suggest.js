// 入力候補（サジェスト）
// <input data-suggest="city|spot" data-lat="" data-lng=""> に候補のドロップダウンを付ける
// 候補を選ぶと、同じフォームの name="lat" / name="lng" / name="country" に値を入れ（あれば）、
// input 要素で "suggest:select" イベントを発行する

document.querySelectorAll('input[data-suggest]').forEach((input) => {
    const type = input.dataset.suggest;
    const form = input.form;
    const latInput = form.querySelector('input[name="lat"]');
    const lngInput = form.querySelector('input[name="lng"]');

    // 候補リストの要素を作る
    const wrap = document.createElement('div');
    wrap.className = 'suggest-wrap';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);

    const list = document.createElement('ul');
    list.className = 'suggest-list';
    list.id = `suggest-${type}-${Math.random().toString(36).slice(2)}`;
    list.setAttribute('role', 'listbox');
    list.hidden = true;
    wrap.appendChild(list);

    input.setAttribute('autocomplete', 'off');
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', list.id);
    input.setAttribute('aria-expanded', 'false');

    let results = [];
    let active = -1;
    let timer = null;
    let composing = false; // 日本語入力の変換中

    const hide = () => {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        active = -1;
    };

    const render = () => {
        list.innerHTML = '';
        results.forEach((r, i) => {
            const li = document.createElement('li');
            li.setAttribute('role', 'option');
            li.className = i === active ? 'active' : '';
            const name = document.createElement('strong');
            name.textContent = r.name;
            const sub = document.createElement('span');
            sub.textContent = r.sub;
            li.append(name, sub);
            // blur より先に選択させるため mousedown を使う
            li.addEventListener('mousedown', (e) => {
                e.preventDefault();
                select(i);
            });
            list.appendChild(li);
        });
        list.hidden = results.length === 0;
        input.setAttribute('aria-expanded', String(!list.hidden));
    };

    const select = (i) => {
        const r = results[i];
        input.value = r.name;
        // おおよその位置（都道府県など）は座標を保存せず、サーバー側で調べ直す
        if (latInput && lngInput && !r.approx) {
            latInput.value = r.lat;
            lngInput.value = r.lng;
        }
        const countryInput = form.querySelector('input[name="country"]');
        if (countryInput) countryInput.value = r.country ?? '';
        hide();
        // 地図などがこのイベントを受け取って、選んだ場所へ移動する
        input.dispatchEvent(new CustomEvent('suggest:select', { detail: r }));
    };

    const search = async () => {
        const q = input.value.trim();
        if (q === '') {
            hide();
            return;
        }
        const params = new URLSearchParams({ type, q });
        if (input.dataset.lat) params.set('lat', input.dataset.lat);
        if (input.dataset.lng) params.set('lng', input.dataset.lng);
        try {
            const res = await fetch(`suggest.php?${params}`);
            const data = await res.json();
            if (input.value.trim() !== q) return; // 古い結果は捨てる
            results = data;
            active = -1;
            render();
        } catch {
            hide();
        }
    };

    input.addEventListener('input', () => {
        // 手で書き換えたら、前に選んだ候補の座標は消す
        if (latInput) latInput.value = '';
        if (lngInput) lngInput.value = '';
        const countryInput = form.querySelector('input[name="country"]');
        if (countryInput) countryInput.value = '';
        if (composing) return;
        clearTimeout(timer);
        timer = setTimeout(search, 300);
    });
    input.addEventListener('compositionstart', () => { composing = true; });
    input.addEventListener('compositionend', () => {
        composing = false;
        clearTimeout(timer);
        timer = setTimeout(search, 300);
    });

    input.addEventListener('keydown', (e) => {
        if (list.hidden || e.isComposing) return;
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            const step = e.key === 'ArrowDown' ? 1 : -1;
            active = (active + step + results.length) % results.length;
            render();
        } else if (e.key === 'Enter' && active >= 0) {
            e.preventDefault();
            select(active);
        } else if (e.key === 'Escape') {
            hide();
        }
    });
    input.addEventListener('blur', hide);
});
