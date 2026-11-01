import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { STLLoader } from 'three/examples/jsm/loaders/STLLoader.js';

async function geometries(url, kind) {
    const buffer = await (await fetch(url, { credentials: 'same-origin' })).arrayBuffer();
    if (kind === 'stl') {
        return [new STLLoader().parse(buffer)];
    }
    const occt = await (await import('occt-import-js')).default({ locateFile: () => '/js/viewer/occt-import-js.wasm' });
    const result = occt.ReadStepFile(new Uint8Array(buffer), null);
    if (!result.success) {
        throw new Error('Could not read the STEP file');
    }

    return result.meshes.map((m) => {
        const g = new THREE.BufferGeometry();
        g.setAttribute('position', new THREE.Float32BufferAttribute(m.attributes.position.array, 3));
        if (m.attributes.normal) {
            g.setAttribute('normal', new THREE.Float32BufferAttribute(m.attributes.normal.array, 3));
        }
        g.setIndex(m.index.array);
        return g;
    });
}

const UNITS = { mm: 1, cm: 0.1, in: 1 / 25.4, ft: 1 / 304.8 };

function number(v, unit) {
    const places = unit === 'mm' ? 1 : unit === 'cm' ? 2 : 3;

    return (+v.toFixed(places)).toLocaleString(undefined, { maximumFractionDigits: places });
}

/** A readout of the overall size, with a unit picker. STL has no units and STEP is read as millimetres, so sizes are taken as mm. */
function readout(into, size) {
    const bar = document.createElement('div');
    bar.style.cssText = 'display:flex;justify-content:space-between;align-items:center;gap:8px;font:12px ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;border:1px solid #e0e1e4;border-radius:6px;background:#fff;padding:6px 10px;color:#222';
    const text = document.createElement('span');
    const select = document.createElement('select');
    select.setAttribute('aria-label', 'Unit');
    select.style.cssText = 'font:inherit;width:auto;min-width:64px;padding:2px 22px 2px 6px;flex:none';
    Object.keys(UNITS).forEach((u) => select.add(new Option(u, u)));
    select.value = localStorage.getItem('model-unit') in UNITS ? localStorage.getItem('model-unit') : 'mm';
    const draw = () => {
        const u = select.value;
        const [x, y, z] = [size.x, size.y, size.z].map((v) => number(v * UNITS[u], u));
        text.textContent = `X ${x} × Y ${y} × Z ${z} ${u}`;
        text.title = 'Overall size of the model, taken as millimetres';
    };
    select.addEventListener('change', () => {
        localStorage.setItem('model-unit', select.value);
        draw();
    });
    draw();
    bar.append(text, select);
    into.replaceChildren(bar);
    into.hidden = false;

    return bar;
}

/** Shows an STL or STEP file in the container, turnable with the mouse. Returns a function that removes it. */
async function mount(box, url, kind, info) {
    const geos = await geometries(url, kind);
    const scene = new THREE.Scene();
    const group = new THREE.Group();
    const material = new THREE.MeshStandardMaterial({ color: 0x8a9099, metalness: 0.3, roughness: 0.55, side: THREE.DoubleSide });
    geos.forEach((g) => {
        if (!g.attributes.normal) {
            g.computeVertexNormals();
        }
        group.add(new THREE.Mesh(g, material));
    });
    scene.add(group);

    const bounds = new THREE.Box3().setFromObject(group);
    const centre = bounds.getCenter(new THREE.Vector3());
    const dims = bounds.getSize(new THREE.Vector3());
    const size = bounds.getSize(new THREE.Vector3()).length() || 1;
    group.position.sub(centre);
    readout(info, dims);

    scene.add(new THREE.HemisphereLight(0xffffff, 0x555566, 1.6));
    const sun = new THREE.DirectionalLight(0xffffff, 1.8);
    sun.position.set(size, size, size);
    scene.add(sun);

    const camera = new THREE.PerspectiveCamera(40, 1, size / 100, size * 20);
    camera.position.set(size * 0.8, size * 0.6, size * 0.9);
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setPixelRatio(window.devicePixelRatio);
    box.appendChild(renderer.domElement);
    renderer.domElement.style.cssText = 'width:100%;height:100%;display:block;cursor:grab';
    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;

    const fit = () => {
        const w = box.clientWidth || 300;
        const h = box.clientHeight || 220;
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
    };
    fit();
    const observer = new ResizeObserver(fit);
    observer.observe(box);
    let frame = 0;
    const tick = () => {
        frame = requestAnimationFrame(tick);
        controls.update();
        renderer.render(scene, camera);
    };
    tick();

    return () => {
        cancelAnimationFrame(frame);
        observer.disconnect();
        controls.dispose();
        geos.forEach((g) => g.dispose());
        material.dispose();
        renderer.dispose();
        renderer.domElement.remove();
        info.replaceChildren();
        info.hidden = true;
    };
}

export { mount };
