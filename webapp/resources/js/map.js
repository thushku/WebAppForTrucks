// Maps (M12): the live map of running trips and a trip's route replay.
// Leaflet loads only on a page that has a map. Tiles come from OpenStreetMap
// (allowed by the page's security policy); markers are drawn as circles, so
// no image files are needed. Names and numbers from the server are put on
// the map as text, never as HTML.

const maps = document.querySelectorAll('[data-live-map], [data-route-map]');

if (maps.length) {
  Promise.all([import('leaflet'), import('leaflet/dist/leaflet.css')]).then(([L]) => {
    maps.forEach((el) => (el.hasAttribute('data-live-map') ? liveMap(L, el) : routeMap(L, el)));
  });
}

const INDIA = [[8, 68], [34, 97]];
const ink = '#0f1a2b';

function base(L, el) {
  const map = L.map(el, { scrollWheelZoom: el.dataset.wheel === 'on', attributionControl: true });
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18, attribution: '&copy; OpenStreetMap contributors',
  }).addTo(map);
  map.fitBounds(INDIA);
  return map;
}

/** A popup built from text nodes: line 1 strong, the rest plain. */
function popup(lines, link) {
  const box = document.createElement('div');
  lines.filter(Boolean).forEach((text, i) => {
    const line = document.createElement(i === 0 ? 'strong' : 'div');
    line.textContent = text;
    box.append(line);
    if (i === 0) box.append(document.createElement('br'));
  });
  if (link) {
    const a = document.createElement('a');
    a.href = link.url;
    a.textContent = link.text;
    box.append(a);
  }
  return box;
}

function ago(minutes, el) {
  if (minutes === null || minutes === undefined) return el.dataset.never || '—';
  if (minutes < 1) return el.dataset.now || 'now';
  return (el.dataset.minutes || ':n min ago').replace(':n', String(minutes));
}

function liveMap(L, el) {
  const map = base(L, el);
  const layer = L.layerGroup().addTo(map);
  let fitted = false;

  const draw = (positions) => {
    layer.clearLayers();
    const bounds = [];
    positions.filter((p) => p.lat !== null).forEach((p) => {
      const marker = L.circleMarker([p.lat, p.lng], {
        radius: 8, weight: 2, color: '#ffffff', fillOpacity: 1, fillColor: p.quiet ? '#c2410c' : '#2a78d6',
      });
      marker.bindPopup(popup([p.vehicle, p.driver, [p.from, p.to].filter(Boolean).join(' → '), p.speed !== null ? `${Math.round(p.speed)} km/h` : null, ago(p.minutes, el)],
        { url: p.url, text: p.trip }));
      marker.bindTooltip(p.vehicle, { direction: 'top', offset: [0, -8] });
      marker.addTo(layer);
      bounds.push([p.lat, p.lng]);
    });
    if (bounds.length && !fitted) {
      map.fitBounds(bounds, { padding: [40, 40], maxZoom: 11 });
      fitted = true;
    }
    const list = document.querySelector(el.dataset.list || '');
    list?.querySelectorAll('[data-ago]').forEach((cell) => {
      const p = positions.find((x) => String(x.trip_id) === cell.dataset.ago);
      if (p) cell.textContent = ago(p.minutes, el);
    });
  };

  const load = () => fetch(el.dataset.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
    .then((r) => (r.ok ? r.json() : Promise.reject(r.status)))
    .then((data) => draw(data.positions || []))
    .catch(() => {});

  // The first picture from the page itself, then a fresh one every minute.
  try { draw(JSON.parse(el.dataset.positions || '[]')); } catch (e) { /* keep the empty map */ }
  setInterval(load, 60000);
}

function routeMap(L, el) {
  const map = base(L, el);
  fetch(el.dataset.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
    .then((r) => (r.ok ? r.json() : Promise.reject(r.status)))
    .then(({ points, events }) => {
      if (!points.length) {
        el.parentElement.querySelector('[data-route-empty]')?.classList.remove('hidden');
        el.classList.add('hidden');
        return;
      }
      const line = points.map((p) => [p[0], p[1]]);
      L.polyline(line, { color: '#2a78d6', weight: 3, opacity: 0.9 }).addTo(map);
      L.circleMarker(line[0], { radius: 7, weight: 2, color: '#ffffff', fillColor: '#1baf7a', fillOpacity: 1 }).bindTooltip(el.dataset.start || 'Start').addTo(map);
      L.circleMarker(line[line.length - 1], { radius: 7, weight: 2, color: '#ffffff', fillColor: ink, fillOpacity: 1 }).bindTooltip(el.dataset.end || 'Last point').addTo(map);
      events.forEach((e) => {
        L.circleMarker([e.lat, e.lng], { radius: 5, weight: 2, color: '#ffffff', fillColor: '#eda100', fillOpacity: 1 })
          .bindPopup(popup([e.label, e.amount, new Date(e.at).toLocaleString()])).addTo(map);
      });
      map.fitBounds(line, { padding: [30, 30] });

      // The replay: a truck dot that runs along the points; the slider and the play button move it.
      const truck = L.circleMarker(line[0], { radius: 9, weight: 3, color: '#ffffff', fillColor: '#eb6834', fillOpacity: 1 }).addTo(map);
      const box = el.parentElement;
      const slider = box.querySelector('[data-replay-slider]');
      const play = box.querySelector('[data-replay-play]');
      const clock = box.querySelector('[data-replay-time]');
      if (!slider || !play) return;
      slider.max = String(points.length - 1);
      const show = (i) => {
        const p = points[i];
        truck.setLatLng([p[0], p[1]]);
        if (clock) clock.textContent = new Date(p[2]).toLocaleString() + (p[3] !== null ? ` · ${Math.round(p[3])} km/h` : '');
      };
      slider.addEventListener('input', () => show(Number(slider.value)));
      let timer = null;
      play.addEventListener('click', () => {
        if (timer) { clearInterval(timer); timer = null; play.textContent = play.dataset.play; return; }
        if (Number(slider.value) >= points.length - 1) slider.value = '0';
        play.textContent = play.dataset.pause;
        const stepSize = Math.max(1, Math.round(points.length / 300));
        timer = setInterval(() => {
          const next = Math.min(points.length - 1, Number(slider.value) + stepSize);
          slider.value = String(next);
          show(next);
          if (next >= points.length - 1) { clearInterval(timer); timer = null; play.textContent = play.dataset.play; }
        }, 50);
      });
      box.querySelector('[data-replay]')?.classList.remove('hidden');
      show(0);
    })
    .catch(() => {});
}
