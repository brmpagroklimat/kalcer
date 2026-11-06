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
  group: 'prediksi' | 'kalkulator' | 'tanya';
}

export const tools: Tool[] = [
  {
    id: 'prediksi-kekeringan',
    title: 'Prediksi Kekeringan',
    description: 'Perkirakan tingkat risiko kekeringan dari curah hujan dan jumlah hari tanpa hujan.',
    icon: 'drought',
    href: null,
    group: 'prediksi',
  },
  {
    id: 'prediksi-banjir',
    title: 'Prediksi Banjir',
    description: 'Nilai potensi banjir dari hujan 3 hari terakhir, kejenuhan tanah, dan kondisi sungai.',
    icon: 'flood',
    href: null,
    group: 'prediksi',
  },
  {
    id: 'prediksi-opt',
    title: 'Prediksi OPT',
    description: 'Estimasi risiko serangan organisme pengganggu tumbuhan menurut komoditas dan cuaca.',
    icon: 'pest',
    href: null,
    group: 'prediksi',
  },
  {
    id: 'kalkulator-air',
    title: 'Kalkulator Kebutuhan Air',
    description: 'Hitung kebutuhan air tanaman per fase tumbuh untuk menjadwalkan pengairan.',
    icon: 'water',
    href: null,
    group: 'kalkulator',
  },
  {
    id: 'kalkulator-pupuk',
    title: 'Kalkulator Pupuk',
    description: 'Hitung kebutuhan Urea, SP-36, dan KCl berdasarkan komoditas, luas lahan, dan target hasil.',
    icon: 'fertilizer',
    href: null,
    group: 'kalkulator',
  },
  {
    id: 'tanya-kalcer',
    title: 'Tanya KALCER',
    description: 'Tanyakan soal waktu tanam, air, atau pupuk dan dapatkan jawaban langsung lewat chat.',
    icon: 'chat',
    href: null,
    group: 'tanya',
  },
];

export const byGroup = (group: Tool['group']) => tools.filter((t) => t.group === group);
export const chatTool = tools.find((t) => t.id === 'tanya-kalcer')!;
