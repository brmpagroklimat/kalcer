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
    description: 'Risiko kekeringan agronomis berdasarkan tren curah hujan, indeks kehijauan vegetasi, dan kelembapan tanah.',
    icon: 'drought',
    href: 'https://kekeringan.agroklimat.id',
  },
  {
    id: 'prediksi-banjir',
    title: 'Prediksi Banjir',
    description: 'Potensi banjir di lahan sawah melalui pendekatan meteorologis, topografi, dan tipe agroekosistem.',
    icon: 'flood',
    href: 'https://banjir.agroklimat.id',
  },
  {
    id: 'prediksi-opt',
    title: 'Prediksi OPT',
    description: 'Risiko serangan organisme pengganggu tanaman berdasarkan parameter cuaca.',
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
    href: 'https://gita.agroklimat.id/',
  },
];

export const chatTool = tools.find((t) => t.id === 'tanya-kalcer')!;
