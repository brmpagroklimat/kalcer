// Daftar alat KALCER. Ubah `href` di sini untuk mengganti tujuan tiap kartu.
// `href: null` berarti alat belum tersedia: kartu tampil dengan label "Segera hadir"
// dan tidak bisa diklik.

export type IconName = 'drought' | 'flood' | 'pest' | 'water' | 'fertilizer' | 'chat';

export interface Tool {
  id: string;
  title: string;
  description: string;
  icon: IconName;
  href: string | null;
}

export const tools: Tool[] = [
  {
    id: 'prediksi-kekeringan',
    title: 'Prediksi Kekeringan',
    description: 'Perkirakan tingkat risiko kekeringan dari curah hujan dan hari tanpa hujan.',
    icon: 'drought',
    href: 'https://kekeringan.agroklimat.id',
  },
  {
    id: 'prediksi-banjir',
    title: 'Prediksi Banjir',
    description: 'Nilai potensi banjir dari hujan 3 hari, kejenuhan tanah, dan kondisi sungai.',
    icon: 'flood',
    href: null,
  },
  {
    id: 'prediksi-opt',
    title: 'Prediksi OPT',
    description: 'Estimasi risiko serangan organisme pengganggu tumbuhan menurut komoditas dan cuaca.',
    icon: 'pest',
    href: null,
  },
  {
    id: 'kalkulator-air',
    title: 'Kalkulator Kebutuhan Air',
    description: 'Hitung kebutuhan air tanaman per fase tumbuh untuk menjadwalkan pengairan.',
    icon: 'water',
    href: 'https://air.agroklimat.id',
  },
  {
    id: 'kalkulator-pupuk',
    title: 'Kalkulator Pupuk',
    description: 'Hitung kebutuhan Urea, SP-36, dan KCl berdasarkan komoditas, luas lahan, dan target hasil.',
    icon: 'fertilizer',
    href: 'https://pemupukan.agroklimat.id',
  },
  {
    id: 'tanya-kalcer',
    title: 'Tanya Gita',
    description: 'Gerbang Informasi Teknologi Agroklimat. Tanya jawab langsung lewat chat.',
    icon: 'chat',
    href: null,
  },
];

export const chatTool = tools.find((t) => t.id === 'tanya-kalcer')!;
