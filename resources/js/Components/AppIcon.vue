<script>
export default { inheritAttrs: false };
</script>

<script setup>
// AppIcon — penghubung tunggal ke pustaka ikon (@lucide/vue).
// Semua halaman memakai <AppIcon name="..."/> tanpa import per file:
// ikon di-import di sini per-nama (tree-shake), bukan `import *`.
//
// `name` memakai kunci berbahasa (kebab/underscore bebas, dicek case-insensitive)
// agar pemelihara pemerintah mengganti ikon di SATU tempat (REGISTRY di bawah)
// tanpa menyentuh template. Ikon yang tak dikenal → gagal keras, bukan blank diam.
import {
    AlertTriangle,
    Archive,
    ArrowLeft,
    ArrowRight,
    Camera,
    CheckCircle,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    CircleSlash,
    Clock,
    Database,
    Download,
    Lock,
    Eye,
    EyeOff,
    File,
    FileCheck,
    FileDown,
    FilePlus,
    FileText,
    FileX,
    Folder,
    FileSpreadsheet,
    Grid3x3,
    HeartHandshake,
    HelpCircle,
    History,
    Home,
    Image,
    Images,
    KeyRound,
    Landmark,
    Layers,
    LayoutDashboard,
    Loader,
    LogOut,
    MapPin,
    Menu,
    MoreHorizontal,
    Package,
    PanelLeft,
    PanelsTopLeft,
    PenLine,
    Pencil,
    Plus,
    Printer,
    QrCode,
    RefreshCw,
    ScanBarcode,
    Search,
    Server,
    Settings,
    ShieldCheck,
    SlidersHorizontal,
    SquarePen,
    Star,
    Trash2,
    TrendingUp,
    Undo2,
    User,
    Users,
    X,
} from '@lucide/vue';

const REGISTRY = {
    // kunci → komponen Lucide
    close: X,
    'chevron-down': ChevronDown,
    'chevron-left': ChevronLeft,
    'chevron-right': ChevronRight,
    menu: Menu,
    collapse: PanelLeft,
    dashboard: LayoutDashboard,
    archive: Archive,
    plus: Plus,
    scan: ScanBarcode,
    rak: Layers,
    users: Users,
    activity: History,
    backup: Database,
    carousel: Images,
    manfaat: Star,
    alur: PenLine,
    faq: HelpCircle,
    gear: Settings,
    logout: LogOut,
    avatar: User,

    // kunci seksi (Sidebar seksiMeta().icon)
    building: Landmark,
    home: Home,
    globe: TrendingUp,
    heart: HeartHandshake,
    folder: Folder,

    // kunci kartu manfaat (Fitur / HalamanPublik ikonLabel)
    pin: MapPin,
    circulation: RefreshCw,
    shield: ShieldCheck,
    qr: QrCode,
    history: History,

    // ikon kontekstual
    eye: Eye,
    'eye-off': EyeOff,
    edit: SquarePen,
    'edit-pen': Pencil,
    'pencil': Pencil,
    'pencil-square': SquarePen,
    trash: Trash2,
    'trash-2': Trash2,
    more: MoreHorizontal,
    'more-horizontal': MoreHorizontal,
    grid: Grid3x3,
    'layout-grid': Grid3x3,
    'layout': PanelsTopLeft,
    key: KeyRound,
    download: Download,
    search: Search,
    filter: SlidersHorizontal,
    sliders: SlidersHorizontal,
    'sliders-horizontal': SlidersHorizontal,
    'arrow-left': ArrowLeft,
    'arrow-right': ArrowRight,
    back: ArrowLeft,
    file: File,
    'file-text': FileText,
    'file-down': FileDown,
    'file-x': FileX,
    'file-check': FileCheck,
    'file-plus': FilePlus,
    'undo': Undo2,
    'kembalikan': Undo2,
    'tersedia': CheckCircle,
    'check-circle': CheckCircle,
    'hilang': CircleSlash,
    'circle-slash': CircleSlash,
    print: Printer,
    'printer': Printer,
    warning: AlertTriangle,
    'alert-triangle': AlertTriangle,
    danger: AlertTriangle,
    loader: Loader,
    spinner: Loader,
    image: Image,
    upload: Download,
    camera: Camera,
    box: Package,
    server: Server,
    clock: Clock,
    star: Star,
    help: HelpCircle,
    'help-circle': HelpCircle,
    lock: Lock,
    arsip: Archive,
    dipinjam: RefreshCw,
    excel: FileSpreadsheet,
};

const props = defineProps({
    name: {
        type: String,
        required: true,
    },
    size: {
        type: [Number, String],
        default: 20,
    },
    strokeWidth: {
        type: [Number, String],
        default: 1.8,
    },
    label: {
        type: String,
        default: '',
    },
    // Ikon dekoratif (di samping teks yang sudah bermakna) → aria-hidden.
    decorative: {
        type: Boolean,
        default: false,
    },
    // Ikon di tombol tanpa teks → butuh aria-label.
    'aria-label': {
        type: String,
        default: undefined,
    },
});

const Ikon = () => {
    const key = String(props.name).trim();
    if (!key) return null;
    const resolved = REGISTRY[key.toLowerCase()] ?? REGISTRY[key];
    if (!resolved) {
        if (import.meta.env.DEV) {
            console.warn(
                `[AppIcon] name="${key}" tidak ada di REGISTRY. Kunci tersedia: ${Object.keys(REGISTRY).join(', ')}`,
            );
        }
        return null;
    }
    return resolved;
};
</script>

<template>
    <component
        :is="Ikon()"
        :size="size"
        :stroke-width="strokeWidth"
        :aria-hidden="decorative ? 'true' : undefined"
        :aria-label="decorative ? undefined : (label || $attrs['aria-label'])"
        :role="decorative || label ? undefined : 'img'"
        v-bind="$attrs"
    />
</template>
