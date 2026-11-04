#!/usr/bin/env node
/**
 * Genera resources/iconos/catalogo.json (biblioteca de íconos de los
 * editores de Página de enlaces y Fidelidad) a partir de:
 *
 * - scripts/iconos/mapa.mjs: categorías, nombres y palabras en español
 *   (curado a mano).
 * - scripts/iconos/propios.mjs: íconos dibujados por DASTI.
 * - node_modules/@lucide/vue (ISC): trazos de los íconos de Lucide.
 *
 * El JSON se versiona en el repositorio: PHP lo lee para validar y para
 * incrustar los trazos usados en las páginas públicas sin Node.
 *
 * Uso: node scripts/generar-catalogo-iconos.mjs
 */
import {
    readdirSync,
    readFileSync,
    writeFileSync,
    copyFileSync,
} from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { CATEGORIAS, NOMBRES, REDES } from './iconos/mapa.mjs';
import { PROPIOS } from './iconos/propios.mjs';

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..');
const carpetaLucide = join(raiz, 'node_modules/@lucide/vue/dist/esm/icons');
const destino = join(raiz, 'resources/iconos');

const ETIQUETAS = new Set([
    'path',
    'circle',
    'rect',
    'line',
    'polyline',
    'polygon',
    'ellipse',
]);
const ATRIBUTOS = new Set([
    'd',
    'cx',
    'cy',
    'r',
    'rx',
    'ry',
    'x',
    'y',
    'x1',
    'y1',
    'x2',
    'y2',
    'width',
    'height',
    'points',
    'fill',
]);
const NOMBRE_VALIDO = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

/** Traducción de las palabras de los nombres de Lucide (íconos no curados). */
const PALABRAS = {
    a: 'a',
    add: 'agregar',
    air: 'aire',
    alarm: 'alarma',
    alert: 'alerta',
    align: 'alinear',
    all: 'todo',
    anchor: 'ancla',
    angle: 'ángulo',
    app: 'aplicación',
    archive: 'archivo',
    arrow: 'flecha',
    arrows: 'flechas',
    audio: 'audio',
    axis: 'eje',
    baby: 'bebé',
    back: 'atras',
    badge: 'insignia',
    bag: 'bolsa',
    ball: 'balon',
    ban: 'prohibido',
    bar: 'barra',
    battery: 'batería',
    bell: 'campana',
    between: 'entre',
    big: 'grande',
    bike: 'bicicleta',
    bird: 'pájaro',
    blocks: 'bloques',
    board: 'tablero',
    bold: 'negrita',
    bolt: 'tornillo',
    book: 'libro',
    bookmark: 'marcador',
    bot: 'robot',
    bottle: 'botella',
    bottom: 'abajo',
    box: 'caja',
    boxes: 'cajas',
    braces: 'llaves',
    brackets: 'corchetes',
    brain: 'cerebro',
    branch: 'rama',
    brick: 'ladrillo',
    bring: 'traer',
    brush: 'cepillo',
    bubble: 'burbuja',
    bug: 'insecto',
    building: 'edificio',
    bus: 'autobús',
    cable: 'cable',
    cake: 'pastel',
    calendar: 'calendario',
    calendars: 'calendarios',
    call: 'llamada',
    camera: 'cámara',
    can: 'lata',
    candy: 'dulce',
    cap: 'gorra',
    car: 'auto',
    card: 'tarjeta',
    cards: 'cartas',
    cart: 'carrito',
    case: 'estuche',
    cast: 'transmitir',
    cat: 'gato',
    ccw: 'antihorario',
    center: 'centro',
    chart: 'gráfica',
    check: 'palomita',
    checks: 'palomitas',
    chef: 'chef',
    chess: 'ajedrez',
    chevron: 'flecha',
    chevrons: 'flechas',
    circle: 'círculo',
    circles: 'círculos',
    clipboard: 'portapapeles',
    clock: 'reloj',
    close: 'cerrar',
    closed: 'cerrado',
    cloud: 'nube',
    code: 'código',
    cog: 'engrane',
    coins: 'monedas',
    column: 'columna',
    columns: 'columnas',
    combine: 'combinar',
    compass: 'brújula',
    cone: 'cono',
    connected: 'conectado',
    contact: 'contacto',
    copy: 'copiar',
    corner: 'esquina',
    cpu: 'procesador',
    credit: 'crédito',
    cross: 'cruz',
    crossed: 'cruzado',
    crown: 'corona',
    cup: 'taza',
    cursor: 'cursor',
    cw: 'horario',
    dashed: 'punteado',
    database: 'base de datos',
    days: 'días',
    decrease: 'disminuir',
    decreasing: 'descendente',
    delete: 'borrar',
    diamond: 'rombo',
    dice: 'dado',
    diff: 'diferencia',
    disc: 'disco',
    divide: 'dividir',
    dog: 'perro',
    dollar: 'dólar',
    door: 'puerta',
    dot: 'punto',
    double: 'doble',
    down: 'abajo',
    download: 'descargar',
    drive: 'unidad',
    droplet: 'gota',
    ear: 'oído',
    earth: 'tierra',
    edit: 'editar',
    egg: 'huevo',
    ellipsis: 'puntos',
    end: 'fin',
    equal: 'igual',
    eraser: 'borrador',
    euro: 'euro',
    exit: 'salir',
    expand: 'expandir',
    eye: 'ojo',
    face: 'cara',
    fan: 'abanico',
    file: 'archivo',
    files: 'archivos',
    film: 'película',
    filter: 'filtro',
    fire: 'fuego',
    fish: 'pez',
    flag: 'bandera',
    flame: 'flama',
    flask: 'matraz',
    flower: 'flor',
    fold: 'doblar',
    folder: 'carpeta',
    folders: 'carpetas',
    forward: 'adelante',
    frame: 'marco',
    from: 'desde',
    front: 'frente',
    full: 'lleno',
    funnel: 'embudo',
    game: 'juego',
    gamepad: 'control',
    gauge: 'medidor',
    gem: 'gema',
    gift: 'regalo',
    git: 'git',
    glass: 'vaso',
    glasses: 'lentes',
    globe: 'globo',
    graduation: 'graduación',
    grid: 'cuadrícula',
    grip: 'agarre',
    group: 'grupo',
    half: 'mitad',
    hand: 'mano',
    hard: 'disco',
    hash: 'numeral',
    hat: 'sombrero',
    heading: 'título',
    headphones: 'audifonos',
    heart: 'corazon',
    help: 'ayuda',
    hexagon: 'hexágono',
    high: 'alto',
    horizontal: 'horizontal',
    hospital: 'hospital',
    hourglass: 'reloj de arena',
    house: 'casa',
    ice: 'hielo',
    image: 'imagen',
    images: 'imágenes',
    in: 'dentro',
    inbox: 'bandeja',
    increase: 'aumentar',
    increasing: 'ascendente',
    info: 'información',
    inside: 'dentro',
    italic: 'cursiva',
    justify: 'justificar',
    key: 'llave',
    keyboard: 'teclado',
    lamp: 'lámpara',
    landmark: 'monumento',
    laptop: 'laptop',
    large: 'grande',
    layers: 'capas',
    layout: 'diseño',
    leaf: 'hoja',
    left: 'izquierda',
    letter: 'letra',
    library: 'biblioteca',
    life: 'vida',
    light: 'luz',
    line: 'línea',
    lines: 'líneas',
    link: 'enlace',
    list: 'lista',
    loader: 'carga',
    lock: 'candado',
    locked: 'bloqueado',
    log: 'registro',
    low: 'bajo',
    mail: 'correo',
    mails: 'correos',
    map: 'mapa',
    mark: 'marca',
    maximize: 'maximizar',
    medium: 'medio',
    menu: 'menú',
    merge: 'unir',
    message: 'mensaje',
    messages: 'mensajes',
    mic: 'micrófono',
    milk: 'leche',
    minimal: 'minimo',
    minimize: 'minimizar',
    minus: 'menos',
    monitor: 'monitor',
    moon: 'luna',
    more: 'más',
    mountain: 'montaña',
    mouse: 'ratón',
    move: 'mover',
    music: 'música',
    narrow: 'estrecho',
    navigation: 'navegación',
    network: 'red',
    no: 'sin',
    not: 'no',
    note: 'nota',
    notebook: 'cuaderno',
    notes: 'notas',
    off: 'apagado',
    open: 'abierto',
    out: 'fuera',
    package: 'paquete',
    panel: 'panel',
    panels: 'paneles',
    paper: 'papel',
    parking: 'estacionamiento',
    pause: 'pausa',
    pen: 'pluma',
    pencil: 'lápiz',
    percent: 'porcentaje',
    person: 'persona',
    phone: 'teléfono',
    picture: 'imagen',
    pie: 'pastel',
    pill: 'pastilla',
    pin: 'pin',
    plane: 'avión',
    play: 'reproducir',
    plug: 'enchufe',
    plus: 'más',
    pointer: 'puntero',
    pound: 'libra',
    power: 'encendido',
    printer: 'impresora',
    pull: 'extraer',
    question: 'pregunta',
    quote: 'cita',
    radio: 'radio',
    rain: 'lluvia',
    receipt: 'recibo',
    rectangle: 'rectángulo',
    redo: 'rehacer',
    refresh: 'actualizar',
    remove: 'quitar',
    repeat: 'repetir',
    replace: 'reemplazar',
    reply: 'responder',
    request: 'solicitud',
    right: 'derecha',
    rotate: 'girar',
    round: 'redondo',
    route: 'ruta',
    rows: 'filas',
    ruler: 'regla',
    save: 'guardar',
    scale: 'balanza',
    scan: 'escanear',
    school: 'escuela',
    screen: 'pantalla',
    search: 'buscar',
    send: 'enviar',
    server: 'servidor',
    settings: 'ajustes',
    share: 'compartir',
    shield: 'escudo',
    ship: 'barco',
    shirt: 'playera',
    shopping: 'compras',
    signal: 'señal',
    slash: 'diagonal',
    sliders: 'controles',
    small: 'pequeño',
    smartphone: 'celular',
    snow: 'nieve',
    sort: 'ordenar',
    space: 'espacio',
    sparkles: 'destellos',
    speaker: 'bocina',
    split: 'dividir',
    square: 'cuadro',
    squares: 'cuadros',
    star: 'estrella',
    start: 'inicio',
    sticky: 'adhesiva',
    stop: 'detener',
    store: 'tienda',
    sun: 'sol',
    sync: 'sincronizar',
    table: 'tabla',
    tablet: 'tableta',
    tag: 'etiqueta',
    tags: 'etiquetas',
    target: 'objetivo',
    terminal: 'terminal',
    text: 'texto',
    thermometer: 'termómetro',
    thumbs: 'pulgar',
    ticket: 'boleto',
    tickets: 'boletos',
    timer: 'temporizador',
    to: 'a',
    top: 'arriba',
    train: 'tren',
    trash: 'basura',
    tree: 'árbol',
    trees: 'árboles',
    trending: 'tendencia',
    triangle: 'triángulo',
    truck: 'camión',
    tv: 'televisión',
    type: 'tipo',
    umbrella: 'paraguas',
    undo: 'deshacer',
    unlink: 'desvincular',
    unlock: 'desbloquear',
    up: 'arriba',
    upload: 'subir',
    user: 'usuario',
    users: 'usuarios',
    utensils: 'cubiertos',
    vertical: 'vertical',
    video: 'video',
    volume: 'volumen',
    wallet: 'cartera',
    wand: 'varita',
    warning: 'aviso',
    watch: 'reloj',
    waves: 'olas',
    wide: 'ancho',
    wifi: 'wifi',
    wind: 'viento',
    window: 'ventana',
    wine: 'vino',
    wrench: 'llave inglesa',
    x: 'x',
    zap: 'rayo',
    zoom: 'zoom',
    zodiac: 'zodiaco',
};

const capitalizar = (t) => t.charAt(0).toUpperCase() + t.slice(1);

function normalizar(texto) {
    return texto
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
}

function entrada(texto, clave) {
    const [nombre, palabras = ''] = String(texto)
        .split('|')
        .map((s) => s.trim());

    if (!nombre) {
        throw new Error(`El ícono «${clave}» no tiene nombre en el mapa.`);
    }

    return { nombre, palabras: normalizar(palabras) };
}

function nombreAutomatico(nombreLucide) {
    const partes = nombreLucide.split('-').map((t) => PALABRAS[t] ?? t);

    return capitalizar(partes.join(' '));
}

function limpiarNodos(nodos, clave) {
    return nodos.map(([etiqueta, atributos]) => {
        if (!ETIQUETAS.has(etiqueta)) {
            throw new Error(
                `Etiqueta no permitida «${etiqueta}» en «${clave}».`,
            );
        }

        const limpio = {};

        for (const [nombre, valor] of Object.entries(atributos)) {
            if (nombre === 'key') {
                continue;
            }

            if (!ATRIBUTOS.has(nombre)) {
                throw new Error(
                    `Atributo no permitido «${nombre}» en «${clave}».`,
                );
            }

            if (
                nombre === 'fill'
                    ? valor !== 'currentColor' && valor !== 'none'
                    : !/^[0-9a-zA-Z .,-]*$/.test(String(valor))
            ) {
                throw new Error(`Valor no permitido en «${clave}».`);
            }

            limpio[nombre] = typeof valor === 'number' ? valor : String(valor);
        }

        return [etiqueta, limpio];
    });
}

async function lucide() {
    const iconos = new Map();
    const archivos = readdirSync(carpetaLucide).filter(
        (a) => a.endsWith('.mjs') && a !== 'index.mjs',
    );

    for (const archivo of archivos) {
        const fuente = readFileSync(join(carpetaLucide, archivo), 'utf8');

        if (!fuente.includes('__iconData')) {
            continue;
        }

        const modulo = await import(
            pathToFileURL(join(carpetaLucide, archivo)).href
        );
        const datos = modulo.__iconData;

        if (datos?.name && Array.isArray(datos.node)) {
            iconos.set(datos.name, datos.node);
        }
    }

    return iconos;
}

const trazos = await lucide();
const iconos = {};

for (const [nombre, nodos] of Object.entries(PROPIOS)) {
    if (trazos.has(nombre)) {
        throw new Error(
            `El ícono propio «${nombre}» choca con un nombre de Lucide.`,
        );
    }

    if (!NOMBRE_VALIDO.test(nombre)) {
        throw new Error(`Nombre inválido «${nombre}».`);
    }

    const { nombre: visible, palabras } = entrada(
        NOMBRES[nombre] ?? '',
        nombre,
    );
    iconos[nombre] = {
        nombre: visible,
        palabras,
        propio: true,
        nodos: limpiarNodos(nodos, nombre),
    };
}

const desconocidos = Object.keys(NOMBRES).filter(
    (n) => !trazos.has(n) && !(n in PROPIOS),
);

if (desconocidos.length > 0) {
    throw new Error(
        `Íconos del mapa que no existen en Lucide: ${desconocidos.join(', ')}`,
    );
}

for (const [nombre, nodos] of [...trazos].sort(([a], [b]) =>
    a.localeCompare(b),
)) {
    if (!NOMBRE_VALIDO.test(nombre)) {
        continue;
    }

    const curado = NOMBRES[nombre];
    const { nombre: visible, palabras } = curado
        ? entrada(curado, nombre)
        : { nombre: nombreAutomatico(nombre), palabras: '' };
    const ingles = nombre.replaceAll('-', ' ');

    iconos[nombre] = {
        nombre: visible,
        palabras: normalizar(
            `${palabras} ${curado ? '' : normalizar(nombreAutomatico(nombre))} ${ingles}`,
        ),
        ...(curado ? {} : { auto: true }),
        nodos: limpiarNodos(nodos, nombre),
    };
}

const redes = {};

for (const [red, texto] of Object.entries(REDES)) {
    redes[red] = entrada(texto, `red:${red}`);
}

const categorias = CATEGORIAS.map(([clave, nombre, lista]) => {
    const vistos = new Set();

    for (const valor of lista) {
        if (vistos.has(valor)) {
            throw new Error(
                `«${valor}» está repetido en la categoría «${String(clave)}».`,
            );
        }

        vistos.add(valor);

        if (
            valor.startsWith('red:')
                ? !(valor.slice(4) in redes)
                : !(valor in iconos)
        ) {
            throw new Error(
                `«${valor}» (categoría «${String(clave)}») no existe.`,
            );
        }
    }

    return { clave, nombre, iconos: lista };
});

// Todos los íconos de Lucide que no están en ninguna categoría curada.
const enCategorias = new Set(categorias.flatMap((c) => c.iconos));
categorias.push({
    clave: 'más',
    nombre: 'Más íconos',
    iconos: Object.keys(iconos).filter((n) => !enCategorias.has(n)),
});

const catalogo = {
    version: 1,
    fuente: 'Lucide (ISC) + íconos propios de DASTI. Generado por scripts/generar-catalogo-iconos.mjs; no editar a mano.',
    categorias,
    redes,
    iconos,
};

writeFileSync(join(destino, 'catalogo.json'), `${JSON.stringify(catalogo)}\n`);
copyFileSync(
    join(raiz, 'node_modules/@lucide/vue/LICENSE'),
    join(destino, 'LICENSE-lucide.txt'),
);

const total = Object.keys(iconos).length;
console.log(
    `Catálogo generado: ${total} íconos (${Object.keys(PROPIOS).length} propios), ${categorias.length} categorías.`,
);

for (const categoria of categorias) {
    console.log(`  ${String(categoria.nombre)}: ${categoria.iconos.length}`);
}
