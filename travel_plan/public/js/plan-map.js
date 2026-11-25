// プラン詳細の地図
//   - 日程のスポットを番号付きのピンで表示し、日ごとに色を分けて線でつなぐ
//   - 行き先を 🚩 で表示
//   - おすすめスポットを地図上に表示し、ポップアップから日程に追加できる
// PHP から渡されたデータは <script id="map-data" type="application/json"> に入っている

const config = JSON.parse(document.getElementById('map-data').textContent);
const { center, pins, planId, csrfToken, days, addedPlaces, categories } = config;

const map = L.map('map');
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors | スポット: Wikipedia',
}).addTo(map);

// ---------- 距離の計算（ハバーサイン公式・km） ----------
const distance = (a, b) => {
    const rad = (d) => (d * Math.PI) / 180;
    const dLat = rad(b.lat - a.lat);
    const dLng = rad(b.lng - a.lng);
    const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLng / 2) ** 2;
    return 2 * 6371 * Math.asin(Math.sqrt(h));
};
const km = (d) => (d < 1 ? `${Math.round(d * 1000)} m` : `${d.toFixed(1)} km`);

// ---------- 行き先と日程のピン ----------
const colors = ['#0f766e', '#ea580c', '#7c3aed', '#0369a1', '#be123c', '#4d7c0f'];
const colorOf = (day) => colors[(day - 1) % colors.length];

L.marker([center.lat, center.lng], {
    icon: L.divIcon({ className: 'dest-pin', html: '<span>🚩</span>', iconSize: [30, 30], iconAnchor: [8, 28] }),
    zIndexOffset: -100,
}).addTo(map).bindPopup(Object.assign(document.createElement('div'), { textContent: `行き先：${center.name}` }));

pins.forEach((pin) => {
    const icon = L.divIcon({
        className: 'pin',
        html: `<span style="background:${colorOf(pin.day)}">${pin.no}</span>`,
        iconSize: [28, 28],
    });
    const popup = document.createElement('div');
    popup.textContent = pin.label; // textContent で安全に表示
    L.marker([pin.lat, pin.lng], { icon, zIndexOffset: 100 }).addTo(map).bindPopup(popup);
});
[...new Set(pins.map((p) => p.day))].forEach((day) => {
    const line = pins.filter((p) => p.day === day).map((p) => [p.lat, p.lng]);
    L.polyline(line, { color: colorOf(day), weight: 3, dashArray: '6 6' }).addTo(map);
});

// 行き先と、その近く（50km 以内）の日程が収まるように表示
const nearPins = pins.filter((p) => distance(center, p) <= 50);
if (nearPins.length > 0) {
    map.fitBounds([[center.lat, center.lng], ...nearPins.map((p) => [p.lat, p.lng])], { padding: [30, 30], maxZoom: 15 });
} else {
    map.setView([center.lat, center.lng], 13);
}

// ---------- おすすめスポット ----------
const toggle = document.getElementById('spots-toggle');
const filters = document.getElementById('spot-filters');
const hereButton = document.getElementById('spots-here');
const statusText = document.getElementById('spots-status');
const list = document.getElementById('spot-list');
const storageKey = `tabiplan-spots-${planId}`;

const spotLayer = L.layerGroup().addTo(map);
let spots = [];          // { data, marker, li }
let searchCenter = null; // 最後に探した中心
let currentCat = '';
let spotMode = false;

// 表示状態を保存（日程に追加して再読み込みした後も、同じ場所・同じ絞り込みで表示するため）
// 保存できない環境（プライベートブラウズ等）でも動くよう try/catch で囲む
const saveState = () => {
    try {
        const c = map.getCenter();
        sessionStorage.setItem(storageKey, JSON.stringify({
            on: spotMode, lat: c.lat, lng: c.lng, zoom: map.getZoom(), cat: currentCat,
            searchLat: searchCenter?.lat, searchLng: searchCenter?.lng,
        }));
    } catch { /* 何もしない */ }
};
const loadState = () => {
    try {
        return JSON.parse(sessionStorage.getItem(storageKey)) ?? null;
    } catch {
        return null;
    }
};

const iconOf = (cat) => categories[cat].split(' ')[0];

// 一番近い日程のスポット
const nearestPin = (p) => {
    let best = null;
    pins.forEach((pin) => {
        const d = distance(pin, p);
        if (!best || d < best.d) best = { pin, d };
    });
    return best;
};

// ポップアップの中身を DOM で作る（外部データを innerHTML に入れない）
const buildPopup = (s) => {
    const el = (tag, cls, text) => {
        const e = document.createElement(tag);
        if (cls) e.className = cls;
        if (text !== undefined) e.textContent = text;
        return e;
    };
    const box = el('div', 'spot-popup');
    if (s.thumbnail) {
        const img = el('img');
        img.src = s.thumbnail;
        img.alt = '';
        box.appendChild(img);
    }
    box.appendChild(el('strong', '', s.name));
    box.appendChild(el('p', 'small muted', s.description));

    const near = nearestPin(s);
    const dist = el('p', 'small');
    dist.textContent = `🚩 行き先から ${km(distance(center, s))}`;
    if (near) {
        dist.append(el('br'), `📍 ${near.pin.label.replace(/^\d+日目 (\d\d:\d\d )?/, '')} から ${km(near.d)}`);
    }
    box.appendChild(dist);

    const link = el('a', 'small', 'Wikipedia で詳しく見る');
    link.href = s.url;
    link.target = '_blank';
    link.rel = 'noopener';
    box.appendChild(link);

    if (addedPlaces.includes(s.name)) {
        box.appendChild(el('p', 'added', '✓ 日程に追加済み'));
        return box;
    }

    // 日程に追加するフォーム（通常の POST → item_save.php → plan.php#map にリダイレクト）
    const form = el('form', 'spot-add');
    form.method = 'post';
    form.action = 'item_save.php';
    const hidden = { csrf_token: csrfToken, plan_id: planId, place: s.name.slice(0, 50), lat: s.lat, lng: s.lng, return: 'map' };
    Object.entries(hidden).forEach(([name, value]) => {
        const input = el('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    });
    const select = el('select');
    select.name = 'day';
    select.setAttribute('aria-label', '追加する日');
    days.forEach((d) => {
        const o = el('option', '', d.label);
        o.value = d.day;
        select.appendChild(o);
    });
    form.append(select, el('button', 'btn btn-small', '＋ 日程に追加'));
    form.addEventListener('submit', saveState);
    box.appendChild(form);
    return box;
};

const applyFilter = () => {
    spots.forEach(({ data, marker, li }) => {
        const show = currentCat === '' || data.category === currentCat;
        li.hidden = !show;
        if (show) spotLayer.addLayer(marker);
        else spotLayer.removeLayer(marker);
    });
    filters.querySelectorAll('.chip').forEach((chip) => {
        chip.classList.toggle('active', chip.dataset.cat === currentCat);
        const count = chip.dataset.cat === '' ? spots.length : spots.filter((s) => s.data.category === chip.dataset.cat).length;
        chip.querySelector('.count').textContent = count;
        chip.hidden = chip.dataset.cat !== '' && count === 0;
    });
};

const loadSpots = async (lat, lng) => {
    searchCenter = { lat, lng };
    hereButton.hidden = true;
    statusText.textContent = 'スポットを探しています…';
    try {
        const res = await fetch(`spots.php?${new URLSearchParams({ lat, lng })}`);
        if (!res.ok) throw new Error();
        const data = await res.json();

        spotLayer.clearLayers();
        list.innerHTML = '';
        spots = data.map((s) => {
            const added = addedPlaces.includes(s.name);
            const marker = L.marker([s.lat, s.lng], {
                icon: L.divIcon({
                    className: `spot-pin${added ? ' is-added' : ''}`,
                    html: `<span>${iconOf(s.category)}</span>`,
                    iconSize: [30, 30],
                }),
            }).bindPopup(() => buildPopup(s), { maxWidth: 260, minWidth: 220 });

            const li = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            const name = document.createElement('strong');
            name.textContent = `${iconOf(s.category)} ${s.name}${added ? ' ✓' : ''}`;
            const sub = document.createElement('span');
            sub.textContent = `行き先から ${km(distance(center, s))}`;
            button.append(name, sub);
            button.addEventListener('click', () => {
                map.flyTo([s.lat, s.lng], Math.max(map.getZoom(), 15), { duration: 0.6 });
                map.once('moveend', () => marker.openPopup());
            });
            li.appendChild(button);
            list.appendChild(li);
            return { data: s, marker, li };
        });

        filters.hidden = false;
        list.hidden = spots.length === 0;
        statusText.textContent = spots.length
            ? `${spots.length} 件のスポットが見つかりました。ピンをクリックすると詳細と「日程に追加」が出ます。`
            : 'この周辺ではスポットが見つかりませんでした。地図を動かして「この周辺を探す」を押してください。';
        applyFilter();
        saveState();
    } catch {
        statusText.textContent = 'スポットを取得できませんでした。時間をおいて再度お試しください。';
    }
};

const setSpotMode = (on) => {
    spotMode = on;
    toggle.setAttribute('aria-pressed', String(on));
    toggle.textContent = on ? '✕ おすすめスポットを閉じる' : '📍 おすすめスポットを表示';
    if (on) {
        const c = map.getCenter();
        loadSpots(c.lat, c.lng);
    } else {
        spotLayer.clearLayers();
        spots = [];
        list.hidden = true;
        filters.hidden = true;
        hereButton.hidden = true;
        statusText.textContent = '';
        saveState();
    }
};

toggle.addEventListener('click', () => setSpotMode(!spotMode));
hereButton.addEventListener('click', () => {
    const c = map.getCenter();
    loadSpots(c.lat, c.lng);
});
filters.addEventListener('click', (e) => {
    const chip = e.target.closest('.chip');
    if (!chip) return;
    currentCat = chip.dataset.cat;
    applyFilter();
    saveState();
});

// 地図を 2km 以上動かしたら「この周辺を探す」ボタンを出す
map.on('moveend', () => {
    if (!spotMode || !searchCenter) return;
    const c = map.getCenter();
    hereButton.hidden = distance(searchCenter, { lat: c.lat, lng: c.lng }) < 2;
});

// 再読み込み前の状態を復元
const saved = loadState();
if (saved?.on) {
    currentCat = saved.cat ?? '';
    map.setView([saved.lat, saved.lng], saved.zoom);
    spotMode = true;
    toggle.setAttribute('aria-pressed', 'true');
    toggle.textContent = '✕ おすすめスポットを閉じる';
    loadSpots(saved.searchLat ?? saved.lat, saved.searchLng ?? saved.lng);
}
