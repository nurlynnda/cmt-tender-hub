data-dc-script="">
const ICONS = {
  dashboard: '<svg viewBox="0 0 24 24" fill="none" width="100%" height="100%"><rect x="3" y="3" width="7" height="9" rx="1.5" stroke="currentColor" stroke-width="1.8"/><rect x="14" y="3" width="7" height="5" rx="1.5" stroke="currentColor" stroke-width="1.8"/><rect x="14" y="12" width="7" height="9" rx="1.5" stroke="currentColor" stroke-width="1.8"/><rect x="3" y="16" width="7" height="5" rx="1.5" stroke="currentColor" stroke-width="1.8"/></svg>',
  tenders: '<svg viewBox="0 0 24 24" fill="none" width="100%" height="100%"><path d="M6 3h9l5 5v13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.8"/><path d="M8 12h8M8 16h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></path></svg>',
  clock: '<svg viewBox="0 0 24 24" fill="none" width="100%" height="100%"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v5l3.5 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
  award: '<svg viewBox="0 0 24 24" fill="none" width="100%" height="100%"><circle cx="12" cy="9" r="5.5" stroke="currentColor" stroke-width="1.8"/><path d="M8.5 13.5L7 21l5-2.5 5 2.5-1.5-7.5" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
  check: '<svg viewBox="0 0 24 24" fill="none" width="100%" height="100%"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M8 12.5l2.5 2.5L16 9.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  checkSm: '<svg viewBox="0 0 24 24" fill="none" width="12" height="12"><path d="M5 12.5l4.5 4.5L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  clockSm: '<svg viewBox="0 0 24 24" fill="none" width="11" height="11"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.2"/><path d="M12 7v5l3.5 2" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>',
  staff: '<svg viewBox="0 0 24 24" fill="none" width="100%" height="100%"><circle cx="9" cy="8" r="3.5" stroke="currentColor" stroke-width="1.8"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M16 4.5c1.7.4 3 2 3 3.9s-1.3 3.5-3 3.9M19 14.5c1.8.5 3.2 2.1 3.2 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
  quotation: '<svg viewBox="0 0 24 24" fill="none" width="100%" height="100%"><path d="M7 3h7l5 5v12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M14 3v5h5M9.5 13h5M9.5 17h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
  settings: '<svg viewBox="0 0 24 24" fill="none" width="100%" height="100%"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M19.4 13.5a7.6 7.6 0 0 0 0-3l1.9-1.5-2-3.4-2.2.9a7.6 7.6 0 0 0-2.6-1.5L14 2.5h-4l-.5 2.5a7.6 7.6 0 0 0-2.6 1.5l-2.2-.9-2 3.4L4.6 10.5a7.6 7.6 0 0 0 0 3l-1.9 1.5 2 3.4 2.2-.9a7.6 7.6 0 0 0 2.6 1.5l.5 2.5h4l.5-2.5a7.6 7.6 0 0 0 2.6-1.5l2.2.9 2-3.4-1.9-1.5z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>',
  search: '<svg viewBox="0 0 24 24" fill="none" width="16" height="16"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8"/><path d="M21 21l-4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
  plus: '<svg viewBox="0 0 24 24" fill="none" width="100%" height="100%"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>',
  chevronLeft: '<svg viewBox="0 0 24 24" fill="none" width="100%" height="100%"><path d="M15 5l-7 7 7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  chevronRight: '<svg viewBox="0 0 24 24" fill="none" width="100%" height="100%"><path d="M9 5l7 7-7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  chevronDown: '<svg viewBox="0 0 24 24" fill="none" width="100%" height="100%"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  x: '<svg viewBox="0 0 24 24" fill="none" width="100%" height="100%"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M9 9l6 6M15 9l-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>'
};

const STATUS_MAP = { inprogress: 'In Progress', awarded: 'Awarded', done: 'Done', lost: 'Lost' };

const DOC_TEMPLATE = [
  { name: 'Borang ISI (Tender Form)', meta: 'Required · PDF', fileName: 'Borang ISI.pdf', fileType: 'PDF', size: '1.2 MB' },
  { name: 'Pricing Schedule', meta: 'Required · XLSX', fileName: 'Pricing Schedule.xlsx', fileType: 'XLS', size: '380 KB' },
  { name: 'Company Profile / SSM Registration', meta: 'Required · PDF', fileName: 'Company Profile.pdf', fileType: 'PDF', size: '860 KB' },
  { name: 'Technical Proposal', meta: 'Required · PDF', fileName: 'Technical Proposal.pdf', fileType: 'PDF', size: '2.1 MB' },
  { name: 'Bid Bond / Bank Guarantee', meta: 'Required · PDF', fileName: 'Bid Bond.pdf', fileType: 'PDF', size: '640 KB' },
];
const FILE_BADGE = { PDF: { bg: 'var(--bad-bg)', color: 'var(--bad-ink)' }, XLS: { bg: 'var(--good-bg)', color: 'var(--good-ink)' }, DOC: { bg: 'var(--info-bg)', color: 'var(--info-ink)' } };
const STAFF_COLORS = { 'Ahmad Faizal': 'var(--accent-solid)', 'Nurul Ain': 'var(--accent-solid)', 'Siti Aisyah': 'var(--warn-ink)', 'Muhammad Hafiz': '#3B78E0' };
const MINISTRY_OPTIONS = [
  'KEMENTERIAN PENDIDIKAN MALAYSIA','KEMENTERIAN KESIHATAN','KEMENTERIAN DALAM NEGERI','KEMENTERIAN PERTAHANAN',
  'KEMENTERIAN PENDIDIKAN','KEMENTERIAN KERJA RAYA','JABATAN PERDANA MENTERI','KEMENTERIAN PENDIDIKAN TINGGI',
  'KEMENTERIAN KEMAJUAN DESA DAN WILAYAH','KEMENTERIAN PERALIHAN TENAGA DAN TRANSFORMASI AIR','KEMENTERIAN KEWANGAN',
  'KEMENTERIAN PENGANGKUTAN','KEMENTERIAN PERUMAHAN DAN KERAJAAN TEMPATAN','KEMENTERIAN ALAM SEKITAR DAN AIR',
  'KEMENTERIAN SUMBER MANUSIA','KEMENTERIAN PERTANIAN DAN KETERJAMINAN MAKANAN','KEMENTERIAN BELIA DAN SUKAN',
  'KEMENTERIAN SUMBER ASLI DAN KELESTARIAN ALAM','KEMENTERIAN PERDAGANGAN DALAM NEGERI DAN KOS SARA HIDUP',
  'KEMENTERIAN SUMBER ASLI, ALAM SEKITAR DAN PERUBAHAN IKLIM','KEMENTERIAN PEMBANGUNAN WANITA, KELUARGA DAN MASYARAKAT',
  'KEMENTERIAN PENGAJIAN TINGGI','KEMENTERIAN TENAGA DAN SUMBER ASLI','KEMENTERIAN PEMBANGUNAN LUAR BANDAR',
  'KEMENTERIAN KOMUNIKASI','KEMENTERIAN KOMUNIKASI DAN MULTIMEDIA','KEMENTERIAN PERTANIAN DAN INDUSTRI MAKANAN',
  'KEMENTERIAN SAINS, TEKNOLOGI DAN INOVASI','KEMENTERIAN PERPADUAN NEGARA','KEMENTERIAN PELANCONGAN, SENI DAN BUDAYA',
  'KEMENTERIAN EKONOMI','KEMENTERIAN DIGITAL','KEMENTERIAN KOMUNIKASI DAN DIGITAL','KEMENTERIAN PEMBANGUNAN KERAJAAN TEMPATAN',
  'KEMENTERIAN LUAR NEGERI','KEMENTERIAN SAINS TEKNOLOGI DAN INOVASI','SETIAUSAHA KERAJAAN NEGERI',
  'KEMENTERIAN WILAYAH PERSEKUTUAN','KEMENTERIAN PEMBANGUNAN USAHAWAN DAN KOPERASI',
  'KEMENTERIAN PERDAGANGAN DALAM NEGERI DAN HAL EHWAL PENGGUNA','KEMENTERIAN PELABURAN, PERDAGANGAN DAN INDUSTRI',
  'KEMENTERIAN AIR, TANAH DAN SUMBER ASLI','KEMENTERIAN PERDAGANGAN ANTARABANGSA DAN INDUSTRI',
  'KEMENTERIAN PERLADANGAN DAN KOMODITI','SETIAUSAHA KERAJAAN NEGERI SEMBILAN','KEMENTERIAN PERUSAHAAN PERLADANGAN DAN KOMODITI',
  'KEMENTERIAN HAL EHWAL EKONOMI',
];

const SKIM_RONDAAN_COSTING = [["1. Dokumen Laporan Kajian Awal Sistem Platform Digital Skim Rondaan Sukarela",18599],["2. Menaik Taraf Sistem Platform Digital Skim Rondaan Sukarela Fasa 1 Tahun 2026",10628],["3. Menaik Taraf Sistem Platform Digital Skim Rondaan Sukarela Fasa 2 Tahun 2027",10628],["4. Dokumentasi Mengikut Kaedah Garis Panduan Pembangunan Aplikasi (KRISA) Fasa 1 Tahun 2026",23913],["5. Dokumentasi Mengikut Kaedah Garis Panduan Pembangunan Aplikasi (KRISA) Fasa 2 Tahun 2027",23913],["6. Pengujian dan Pentauliahan",5312],["7. Pengujian Postur Keselamatan (Security Posture Assessment) Bilangan 1 Tahun 2026",3986],["8. Pengujian Postur Keselamatan (Security Posture Assessment) Bilangan 2 Tahun 2027",3986],["9. Latihan dan Training of Trainers (ToT) Siri 1 Tahun 2026",10628],["10. Latihan dan Training of Trainers (ToT) Siri 2 Tahun 2027",10628],["11. Sokongan dan Penyelenggaraan Fasa 1 Tahun 2026",5312],["12. Sokongan dan Penyelenggaraan Fasa 2 Tahun 2027",5312]];
const PD_PROJECTS = [
  { name: 'UMT-NETWORK-10CS', code: 'DEMO-TE2627', stage: 'Won / Execute', won: true, cost: 'RM 408,000.00', budget: 'RM 611,000.00', net: 'RM 232,000.00', cpi: '—' },
  { name: 'Kampus Timur Fibre', code: 'DEMO-PJ-2601', stage: 'Won / Execute', won: true, cost: 'RM 1,239,500.00', budget: 'RM 895,500.00', net: 'RM -864,500.00', netNeg: true, cpi: '—',
    low: { revenue: 'RM 375,000.00', contract: 'RM 1,250,000.00', budgetNet: 'RM 354,500.00', budgetPct: '28.4%', actualNet: 'RM -864,500.00', actualPct: '-230.5%', actualNeg: true } },
  { name: 'Promox quotation, Pre- Membership enhancement V2', code: '', stage: 'Not Won', won: false, cost: 'RM 0.00', budget: 'RM 0.00', net: 'RM 0.00', cpi: '—',
    low: { revenue: 'RM 0.00', contract: 'RM 0.00', budgetNet: 'RM 0.00', budgetPct: '0.0%', actualNet: 'RM 0.00', actualPct: '0.0%', actualNeg: false } },
  { name: 'POJ-RVT2 - PERKHIDMATAN PENYELENGGARAAN SISTEM RVT FASA 2 BAGI TEMPOH 12 BULAN DI MAHKAMAH SELURUH MALAYSIA', code: 'TE2638.POJ-RVT2.SC', stage: 'Won / Execute', won: true, cost: 'RM 351,631.11', budget: 'RM 351,631.11', net: 'RM -351,631.11', netNeg: true, cpi: '—',
    low: { revenue: 'RM 0.00', contract: 'RM 3,907,012.32', budgetNet: 'RM 3,555,381.21', budgetPct: '91.0%', actualNet: 'RM -351,631.11', actualPct: '0.0%', actualNeg: true } },
];
const QUOTE_TPL_DEFAULT = {
  header: { company: 'CMT Sdn. Bhd.', regNo: '201901000000 (1234567-X)', sstNo: '', address: 'Level 8, Menara Example, Jalan Tun Razak,\n50400 Kuala Lumpur, Malaysia', phone: '+603-0000 0000', email: 'sales@cmt.com.my', website: 'www.cmt.com.my' },
  terms: 'Prices quoted are in Ringgit Malaysia (RM).\nThis quotation is valid for the number of days stated above from the date of issue.\nPayment terms: 30 days from the date of invoice.\nDelivery within 4–6 weeks upon receipt of official Purchase Order (PO) / Letter of Award.\nAny changes to scope, quantity or specification may affect the quoted price.\nWarranty as per principal / manufacturer terms unless stated otherwise.',
};
const QUOTE_SEED = [
  { id: 1, no: 'QTN-2026-0012', date: '2026-09-18', validity: '30', status: 'Sent', customer: 'Jabatan Perpaduan Negara dan Integrasi Nasional', attn: 'Puan Rozita binti Hassan, Ketua Unit ICT', custAddress: 'Aras 5, Blok F8, Kompleks F,\nPresint 1, 62000 Putrajaya', subject: 'Supply of network switches and installation for JPNIN HQ', pic: 'Siti Aisyah', picTitle: 'Sales Executive', sst: true, sstRate: '8',
    items: [{ title: '24-port Gigabit PoE+ managed switch', specs: 'Interface: 24× 10/100/1000 Mbps RJ45 PoE+ Ports; 4× Gigabit SFP Slots; 1× RJ45 Console Port; 1× Micro-USB Console Port\nPower Supply: 100–240 V AC, 50/60 Hz, Internal Power Supply\nDimensions (W x D x H): 17.3 x 13.0 x 1.7 in (440 x 330 x 44 mm)\nMounting: 19-inch Rack Mountable (1U)\nMax. Power Consumption: 478.6 W (110V/60Hz @ 25°C, with 384W PD connected); 463.7 W (220V/50Hz @ 25°C, with 384W PD connected)\nSwitching Capacity: 56 Gbps', qty: '6', unit: 'Unit', price: '4850' }, { desc: 'Installation, configuration & testing', qty: '1', unit: 'Lot', price: '6500' }] },
  { id: 2, no: 'QTN-2026-0011', date: '2026-09-10', validity: '30', status: 'Accepted', customer: 'Majlis Perbandaran Klang', attn: 'Encik Faizal bin Omar', custAddress: 'Jalan Perbandaran,\n41675 Klang, Selangor', subject: 'Annual maintenance for CCTV system (12 months)', pic: 'Muhammad Hafiz', picTitle: 'Account Manager', sst: true, sstRate: '8',
    items: [{ desc: 'Preventive maintenance — quarterly visit', qty: '4', unit: 'Visit', price: '2800' }, { desc: 'Corrective maintenance support (on-call)', qty: '12', unit: 'Month', price: '650' }] },
  { id: 3, no: 'QTN-2026-0010', date: '2026-08-04', validity: '30', status: 'Draft', customer: 'Pejabat Daerah Kuantan', attn: '', custAddress: 'Jalan Gambut,\n25000 Kuantan, Pahang', subject: 'Laptop rental for 18 months', pic: 'Ahmad Faizal', picTitle: 'Sales Executive', sst: false, sstRate: '8',
    items: [{ desc: 'Laptop 14" i5 / 16GB / 512GB SSD — rental', qty: '25', unit: 'Unit', price: '1260' }] },
];
function normalizeQuoteItem(it) {
  if (it.desc == null) return it;
  const { desc, ...rest } = it;
  const lines = String(desc).split('\n');
  const repair = it.title == null || (lines.length > 1 && !String(it.specs || '').includes('\n'));
  return repair ? { ...rest, title: lines[0].trim(), specs: lines.slice(1).join('\n') } : { ...rest, title: it.title, specs: it.specs || '' };
}
function normalizeQuotes(qs) {
  let out = qs.map(q => ({ ...q, items: (q.items || []).map(normalizeQuoteItem) }));
  let repaired = false;
  try { repaired = localStorage.getItem('tenderhub-quotes-v2') === '1'; } catch (e) {}
  if (!repaired) {
    out = out.map(q => {
      const seed = QUOTE_SEED.find(x => x.no === q.no);
      if (!seed) return q;
      return { ...q, items: q.items.map(it => { const si = seed.items.find(x => x.title && x.title === it.title && x.specs); return si ? { ...it, specs: si.specs } : it; }) };
    });
    try { localStorage.setItem('tenderhub-quotes-v2', '1'); } catch (e) {}
  }
  return out;
}
function amountInWords(n) {
  const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
  const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
  const w = (x) => x < 20 ? ones[x] : x < 100 ? tens[Math.floor(x / 10)] + (x % 10 ? ' ' + ones[x % 10] : '') : ones[Math.floor(x / 100)] + ' Hundred' + (x % 100 ? ' ' + w(x % 100) : '');
  const big = (x) => { if (x === 0) return 'Zero'; const parts = []; [[1e9, 'Billion'], [1e6, 'Million'], [1e3, 'Thousand'], [1, '']].forEach(([d, l]) => { const c = Math.floor(x / d); if (c) { parts.push(w(c) + (l ? ' ' + l : '')); x -= c * d; } }); return parts.join(' '); };
  const rm = Math.floor(n + 1e-9), sen = Math.round((n - rm) * 100);
  return 'Ringgit Malaysia ' + big(rm) + (sen ? ' and Sen ' + big(sen) : '') + ' Only';
}
const RAW_TENDERS = [
  { woNumber: '200-10092026-001', woDate: '10 Sep 2026', name: 'PERKHIDMATAN PEMBANGUNAN SISTEM PLATFORM DIGITAL SKIM RONDAAN SUKARELA UNTUK JABATAN PERPADUAN NEGARA DAN INTEGRASI NASIONAL FASA 2', code: 'QT260000000041127', category: 'Software Development', agency: 'JABATAN PERPADUAN NEGARA DAN INTEGRASI NASIONAL', oo: 'Siti Aisyah', staff: 'Siti Aisyah', avatarBg: 'var(--accent-solid)', deadline: '15 Oct 2026', daysLeft: '22 days left', created: '10 Sep 2026', urgent: false, value: 'RM 162,006.10', doc: 40, status: 'In Progress', publishDate: '08 Sep 2026', mode: 'EP', type: 'Tender', hasBriefing: 'Yes', briefingDate: '22 Sep 2026', costingPreset: 'skimRondaan', scope: 'Pembangunan Sistem Platform Digital Skim Rondaan Sukarela Fasa 2 merangkumi kajian awal, menaik taraf sistem, dokumentasi KRISA, pengujian keselamatan, latihan ToT serta sokongan dan penyelenggaraan bagi tahun 2026–2027.', notes: 'Costing finalised from BQ; pending document compilation.' },
  { woNumber: '200-18112025-001', woDate: '18 Nov 2025', name: '[FTA/CPTPP](HIJAU) PERKHIDMATAN SEWAAN BERPUSAT PERKAKASAN ICT BAGI 5 ZON SECARA SEWA GUNA UNTUK KEGUNAAN KEMENTERIAN PENDIDIKAN TINGGI (KPT), JABATAN, POLITEKNIK DAN KOLEJ KOMUNITI BAGI ZON SELATAN : JOHOR, MELAKA & N.SEMBILAN', code: 'QT250000000034275', category: 'IT Infrastructure', agency: 'PENGURUSAN AM', oo: 'Nurul Ain', staff: 'Ahmad Faizal', avatarBg: 'var(--accent-solid)', deadline: '05 Jan 2026', daysLeft: '3 days left', created: '20 Jul 2026', urgent: true, value: 'RM 855,283.00', doc: 80, status: 'In Progress', publishDate: '17 Nov 2025', scope: 'Sewaan berpusat perkakasan ICT bagi 5 zon secara sewa guna untuk kegunaan KPT, jabatan, politeknik dan kolej komuniti zon selatan.', notes: 'Technical proposal drafted; awaiting final sign-off from procurement lead before submission.' },
  { woNumber: '200-18112025-002', woDate: '18 Nov 2025', name: '[FTA/CPTPP](HIJAU) PERKHIDMATAN SEWAAN BERPUSAT PERKAKASAN ICT SECARA SEWA GUNA UNTUK KEGUNAAN KEMENTERIAN PENDIDIKAN TINGGI (KPT), JABATAN, POLITEKNIK DAN KOLEJ KOMUNITI BAGI ZON TENGAH : PERAK, SELANGOR, W.P. KUALA LUMPUR DAN W.P. PUTRAJAYA', code: 'QT250000000034274', category: 'IT Infrastructure', agency: 'PENGURUSAN AM', oo: 'Ahmad Faizal', staff: 'Nurul Ain', avatarBg: 'var(--accent-solid)', deadline: '05 Jan 2026', daysLeft: '9 days left', created: '02 Aug 2026', urgent: true, value: 'RM 798,630.00', doc: 45, status: 'In Progress', publishDate: '17 Nov 2025', scope: 'Sewaan berpusat perkakasan ICT secara sewa guna untuk KPT zon tengah: Perak, Selangor, WP Kuala Lumpur dan WP Putrajaya.', notes: 'Pricing schedule pending finance approval. Site visit completed on 5 Sep.' },
  { woNumber: '200-18112025-003', woDate: '18 Nov 2025', name: '[FTA/CPTPP](HIJAU) PERKHIDMATAN SEWAAN BERPUSAT PERKAKASAN ICT SECARA SEWA GUNA UNTUK KEGUNAAN KEMENTERIAN PENDIDIKAN TINGGI (KPT), JABATAN, POLITEKNIK DAN KOLEJ KOMUNITI BAGI ZON TIMUR : KELANTAN, PAHANG & TERENGGANU', code: 'QT250000000034272', category: 'IT Infrastructure', agency: 'PENGURUSAN AM', oo: 'Muhammad Hafiz', staff: 'Nurul Ain', avatarBg: 'var(--accent-solid)', deadline: '05 Jan 2026', daysLeft: '66 days left', created: '10 Aug 2026', urgent: false, value: 'RM 915,961.00', doc: 60, status: 'In Progress', publishDate: '17 Nov 2025', scope: 'Sewaan berpusat perkakasan ICT secara sewa guna untuk KPT zon timur: Kelantan, Pahang & Terengganu.', notes: 'Waiting on vendor quotation for networking hardware to finalize pricing.' },
  { woNumber: '200-18112025-004', woDate: '18 Nov 2025', name: '[FTA/CPTPP](HIJAU) PERKHIDMATAN SEWAAN BERPUSAT PERKAKASAN ICT SECARA SEWA GUNA UNTUK KEGUNAAN KEMENTERIAN PENDIDIKAN TINGGI (KPT), JABATAN, POLITEKNIK DAN KOLEJ KOMUNITI BAGI ZON UTARA : PERLIS, KEDAH & PULAU PINANG', code: 'QT250000000034273', category: 'IT Infrastructure', agency: 'PENGURUSAN AM', oo: 'Siti Aisyah', staff: 'Siti Aisyah', avatarBg: 'var(--warn-ink)', deadline: '05 Jan 2026', daysLeft: '26 days left', created: '25 Aug 2026', urgent: false, value: 'RM 758,973.00', doc: 20, status: 'In Progress', publishDate: '17 Nov 2025', scope: 'Sewaan berpusat perkakasan ICT secara sewa guna untuk KPT zon utara: Perlis, Kedah & Pulau Pinang.', notes: 'Just registered. Borang ISI in progress, other documents not started.' },
  { woNumber: '200-09122025-001', woDate: '09 Dec 2025', name: 'PEROLEHAN PERKHIDMATAN PEMBANGUNAN PERSEKITARAN VIRTUALIZATION DI JIM', code: 'QT250000000035730', category: 'IT Infrastructure', agency: 'BAHAGIAN KEWANGAN', oo: 'Nurul Ain', staff: 'Ahmad Faizal', avatarBg: 'var(--accent-solid)', deadline: '09 Jan 2026', daysLeft: '11 days left', created: '15 Aug 2026', urgent: true, value: 'RM 586,401.00', doc: 55, status: 'In Progress', publishDate: '03 Dec 2025', scope: 'Pembangunan persekitaran virtualization untuk Jabatan Imigresen Malaysia.', notes: 'Server sizing under review with vendor.' },
  { woNumber: '200-15122025-002', woDate: '15 Dec 2025', name: '[HIJAU] PERKHIDMATAN SEWAAN PERALATAN ICT CEKAP TENAGA UNTUK JABATAN PERPADUAN NEGARA DAN INTEGRASI NASIONAL ZON SEMENANJUNG BAGI TEMPOH 42 BULAN', code: 'QT250000000036365', category: 'IT Infrastructure', agency: 'JPNIN IBU PEJABAT', oo: 'Muhammad Hafiz', staff: 'Muhammad Hafiz', avatarBg: '#3B78E0', deadline: '02 Jan 2026', daysLeft: 'Awarded', created: '02 Jun 2026', urgent: false, value: 'RM 718,462.00', doc: 100, status: 'Awarded', publishDate: '11 Dec 2025', scope: 'Sewaan peralatan ICT cekap tenaga untuk JPNIN zon semenanjung bagi tempoh 42 bulan.', notes: 'Contract awarded 2 Sep 2026. All documents submitted and verified.' },
  { woNumber: '200-15122025-006', woDate: '15 Dec 2025', name: 'TENDER PERKHIDMATAN MEMBEKAL, MENGHANTAR, MEMASANG, MENGKONFIGURASI, MENGUJI DAN MENTAULIAH PERKAKASAN KOMPUTER PERIBADI, KOMPUTER GRAFIK, KOMPUTER RIBA DAN PERISIAN SECARA SEWA GUNA DI PUSAT DARAH NEGARA BAGI TEMPOH 36 BULAN', code: 'QT250000000032303', category: 'IT Infrastructure', agency: 'PUSAT DARAH NEGARA', oo: 'Ahmad Faizal', staff: 'Ahmad Faizal', avatarBg: 'var(--accent-solid)', deadline: '08 Jan 2026', daysLeft: 'Awarded', created: '15 May 2026', urgent: false, value: 'RM 614,630.00', doc: 100, status: 'Awarded', publishDate: '13 Dec 2025', scope: 'Bekal, hantar, pasang, konfigurasi, uji dan tauliah komputer peribadi, komputer grafik, komputer riba dan perisian secara sewa guna bagi tempoh 36 bulan.', notes: 'Awarded. Delivery schedule being finalized with client.' },
  { woNumber: '200-15122025-004', woDate: '15 Dec 2025', name: 'PERKHIDMATAN PENYELENGGARAAN SECARA PREVENTIVE DAN CORRECTIVE BAGI PERKAKASAN DAN PERISIAN RANGKAIAN KESELAMATAN DAN RANGKAIAN SETEMPAT UNTUK POLIS DIRAJA MALAYSIA (PDRM)', code: 'QT240000000012703', category: 'IT Infrastructure', agency: 'BAHAGIAN PEROLEHAN', oo: 'Muhammad Hafiz', staff: 'Muhammad Hafiz', avatarBg: '#3B78E0', deadline: '09 Jan 2026', daysLeft: 'Closed', created: '01 Apr 2026', urgent: false, value: 'RM 164,751.00', doc: 100, status: 'Done', publishDate: '10 Dec 2025', scope: 'Penyelenggaraan preventive dan corrective bagi perkakasan dan perisian rangkaian keselamatan dan rangkaian setempat untuk PDRM.', notes: 'Not shortlisted. No further action needed.' },
  { woNumber: '200-15012026-001', woDate: '15 Jan 2026', name: 'PERKHIDMATAN PEROLEHAN DATA LIDAR 3D SCANNING UNTUK KAJIAN PENILAIAN INTEGRITI STRUKTUR TEROWONG SMART BAGI PROJEK RANCANGAN TEBATAN BANJIR UPPER SUNGAI KLANG, WILAYAH PERSEKUTUAN KUALA LUMPUR', code: 'QT250000000036776', category: 'Civil Works', agency: 'JABATAN PENGAIRAN DAN SALIRAN MALAYSIA', oo: 'Siti Aisyah', staff: 'Siti Aisyah', avatarBg: 'var(--warn-ink)', deadline: '04 Feb 2026', daysLeft: 'Closed', created: '02 Mar 2026', urgent: false, value: 'RM 703,487.00', doc: 100, status: 'Done', publishDate: '07 Jan 2026', scope: 'Perolehan data LiDAR 3D scanning untuk kajian penilaian integriti struktur Terowong SMART bagi projek Tebatan Banjir Upper Sungai Klang.', notes: 'Tender closed, no award announcement received to date.' },
  { woNumber: '200-17122025-001', woDate: '17 Dec 2025', name: 'MENAIKTARAF SISTEM RANGKAIAN WiFi DI 5 BLOK BANGUNAN PENTADBIRAN, 2 BLOK BANGUNAN AKADEMIK DAN 3 KAWASAN GUNASAMA BAGI UiTM CAWANGAN NEGERI SEMBILAN KAMPUS KUALA PILAH', code: 'UiTM/N1/PER/T/B/1125/0001', category: 'IT Infrastructure', agency: 'AGENSI PENGUATKUASAAN MARITIM MALAYSIA', oo: 'Nurul Ain', staff: 'Nurul Ain', avatarBg: 'var(--accent-solid)', deadline: '05 Jan 2026', daysLeft: '16 days left', created: '01 Aug 2026', urgent: false, value: 'RM 768,088.00', doc: 35, status: 'In Progress', publishDate: '10 Dec 2025', scope: 'Naik taraf sistem rangkaian WiFi di 5 blok bangunan pentadbiran, 2 blok akademik dan 3 kawasan gunasama, UiTM Kampus Kuala Pilah.', notes: 'Site survey scheduled; pricing in progress.' },
  { woNumber: '200-18122025-001', woDate: '18 Dec 2025', name: 'PERKHIDMATAN SEWAAN PERALATAN SIDANG VIDEO UNTUK AGENSI PENGUATKUASAAN MARITIM MALAYSIA BAGI TEMPOH EMPAT (4) TAHUN', code: 'QT250000000034806', category: 'IT Infrastructure', agency: 'BAHAGIAN PEROLEHAN DAN PENSWASTAAN', oo: 'Muhammad Hafiz', staff: 'Ahmad Faizal', avatarBg: 'var(--accent-solid)', deadline: '02 Jan 2026', daysLeft: '19 days left', created: '18 Aug 2026', urgent: false, value: 'RM 709,514.00', doc: 40, status: 'In Progress', publishDate: '20 Nov 2025', scope: 'Sewaan peralatan sidang video untuk APMM bagi tempoh 4 tahun.', notes: 'Vendor quotation for video conferencing units in progress.' },
  { woNumber: '200-21112025-003', woDate: '21 Nov 2025', name: '[FTA(CPTPP)] PEMBANGUNAN PERKHIDMATAN PENGKOMPUTERAN AWAN PERSENDIRIAN UNTUK KEMENTERIAN KESIHATAN MALAYSIA BAGI TEMPOH KONTRAK 41 BULAN', code: 'QT250000000034176', category: 'IT Infrastructure', agency: 'JKN SELANGOR PEJABAT TIMBALAN PENGARAH KESIHATAN NEGERI SELANGOR', oo: 'Siti Aisyah', staff: 'Muhammad Hafiz', avatarBg: '#3B78E0', deadline: '05 Jan 2026', daysLeft: '21 days left', created: '10 Aug 2026', urgent: false, value: 'RM 386,868.00', doc: 100, status: 'Awarded', publishDate: '15 Dec 2025', scope: 'Pembangunan perkhidmatan pengkomputeran awan persendirian untuk KKM bagi tempoh kontrak 41 bulan.', notes: 'Contract awarded. Delivery and onboarding schedule being finalized.' },
  { woNumber: '200-22122025-003', woDate: '22 Dec 2025', name: 'TENDER PERKHIDMATAN SOKONGAN OPERASI DAN PENYELENGGARAAN BAGI PERKAKASAN ICT, RANGKAIAN, PERISIAN, PUSAT DATA DAN SERVER DI HOSPITAL TANJONG KARANG, SELANGOR KEMENTERIAN KESIHATAN MALAYSIA BAGI TEMPOH KONTRAK 36 BULAN', code: 'QT250000000025524', category: 'IT Infrastructure', agency: 'JKN N SEMBILAN PEJABAT TIMBALAN PENGARAH KESIHATAN NEGERI NEGERI', oo: 'Ahmad Faizal', staff: 'Nurul Ain', avatarBg: 'var(--accent-solid)', deadline: '09 Jan 2026', daysLeft: 'Closed', created: '01 Feb 2026', urgent: false, value: 'RM 735,181.00', doc: 100, status: 'Done', publishDate: '16 Dec 2025', scope: 'Sokongan operasi dan penyelenggaraan bagi perkakasan ICT, rangkaian, perisian, pusat data dan server, Hospital Tanjong Karang bagi tempoh kontrak 36 bulan.', notes: 'Not shortlisted. No further action needed.' },
  { woNumber: '200-22122025-005', woDate: '22 Dec 2025', name: "MENAIKTARAF SISTEM RANGKAIAN WIFI DI 6 BLOK AKADEMIK, 3 BLOK PENTADBIRAN DAN 8 RUANG GUNASAMA DI UiTM CAWANGAN PULAU PINANG KAMPUS PERMATANG PAUH", code: 'UiTM/P1/PER/T/B/1125/0003', category: 'IT Infrastructure', agency: 'BAHAGIAN PEROLEHAN DAN PENSWASTAAN', oo: 'Muhammad Hafiz', staff: 'Siti Aisyah', avatarBg: 'var(--warn-ink)', deadline: '12 Jan 2026', daysLeft: '23 days left', created: '22 Aug 2026', urgent: false, value: 'RM 906,521.00', doc: 15, status: 'In Progress', publishDate: '16 Dec 2025', scope: 'Naik taraf sistem rangkaian WiFi di 6 blok akademik, 3 blok pentadbiran dan 8 ruang gunasama, UiTM Kampus Permatang Pauh.', notes: 'Just registered. Documents pending.' },
  { woNumber: '200-22122025-001', woDate: '22 Dec 2025', name: 'TENDER PERKHIDMATAN PENYELENGGARAAN DAN SOKONGAN OPERASI PERALATAN ICT DAN APLIKASI BAGI LABORATORY INFORMATION SYSTEM (LIS), CENTRAL STERILE SUPPLY SERVICES INFORMATION SYSTEM (CENSSIS) DAN OPERATING THEATRE MANAGEMENT SYSTEM (OTMS) BAGI TEMPOH TIGA (3) TAHUN, HOSPITAL TUANKU JA\'AFAR, SEREMBAN, NEGERI SEMBILAN DARUL KHUSUS', code: 'QT250000000036427', category: 'IT Infrastructure', agency: 'INSTITUT PENYELIDIKAN SAINS DAN TEKNOLOGI PERTAHANAN', oo: 'Nurul Ain', staff: 'Muhammad Hafiz', avatarBg: '#3B78E0', deadline: '13 Jan 2026', daysLeft: '57 days left', created: '20 Aug 2026', urgent: false, value: 'RM 944,438.00', doc: 50, status: 'In Progress', publishDate: '24 Dec 2025', scope: 'Penyelenggaraan dan sokongan operasi peralatan ICT dan aplikasi bagi LIS, CENSSIS dan OTMS bagi tempoh 3 tahun.', notes: 'Technical proposal draft in review.' },
  { woNumber: '200-22122025-006', woDate: '22 Dec 2025', name: 'PERKHIDMATAN MEREKA BENTUK,MEMBANGUN,MEMBEKAL,MEMASANG,MENGUJI DAN MENTAULIAH MAQIS DIGITAL PLATFORM (MDP) SECARA REQUEST FOR PROPOSAL (RFP)', code: 'MAQIS.(S) 400-10/11/1(5)', category: 'IT Infrastructure', agency: 'IBU PEJABAT PROGRAM PERUBATAN PEJABAT AM', oo: 'Siti Aisyah', staff: 'Ahmad Faizal', avatarBg: 'var(--accent-solid)', deadline: '22 Jan 2026', daysLeft: '10 days left', created: '05 Aug 2026', urgent: true, value: 'RM 861,741.00', doc: 65, status: 'In Progress', publishDate: '24 Dec 2025', scope: 'Reka bentuk, bangun, bekal, pasang, uji dan tauliah MAQIS Digital Platform (MDP) secara RFP.', notes: 'RFP response under internal review before submission.' },
  { woNumber: '200-22122025-002', woDate: '22 Dec 2025', name: '[FTA(CPTPP)] PENGGANTIAN DAN PERTAMBAHAN PERKAKASAN SERTA PERISIAN ICT BAGI MENYOKONG PENDIGITALAN PERKHIDMATAN KESIHATAN PERGIGIAN DI KLINIK PERGIGIAN KEMENTERIAN KESIHATAN MALAYSIA BAGI TEMPOH KONTRAK 12 BULAN', code: 'QT250000000035757', category: 'IT Infrastructure', agency: 'BAHAGIAN PEROLEHAN', oo: 'Ahmad Faizal', staff: 'Nurul Ain', avatarBg: 'var(--accent-solid)', deadline: '28 Jan 2026', daysLeft: '22 days left', created: '20 Aug 2026', urgent: false, value: 'RM 271,608.00', doc: 30, status: 'In Progress', publishDate: '24 Dec 2025', scope: 'Penggantian dan pertambahan perkakasan serta perisian ICT bagi menyokong pendigitalan perkhidmatan kesihatan pergigian di klinik pergigian KKM bagi tempoh kontrak 12 bulan.', notes: 'Pricing schedule in progress with vendor quotations.' },
  { woNumber: '200-24122025-001', woDate: '24 Dec 2025', name: 'PERKHIDMATAN SEWAAN LAPAN PULUH DUA (82) UNIT KOMPUTER RIBA BAGI TEMPOH LAPAN BELAS (18) BULAN DI INSTITUT PENYELIDIKAN SAINS DAN TEKNOLOGI PERTAHANAN (STRIDE)', code: 'QT250000000036595', category: 'IT Infrastructure', agency: 'PENGURUSAN AM', oo: 'Muhammad Hafiz', staff: 'Siti Aisyah', avatarBg: 'var(--warn-ink)', deadline: '02 Jan 2026', daysLeft: '33 days left', created: '25 Aug 2026', urgent: false, value: 'RM 232,032.00', doc: 10, status: 'In Progress', publishDate: '24 Dec 2025', scope: 'Sewaan 82 unit komputer riba bagi tempoh 18 bulan di STRIDE.', notes: 'Just registered. Borang ISI to be completed.' },
  { woNumber: '200-26122025-001', woDate: '26 Dec 2025', name: 'PERKHIDMATAN SOKONGAN TEKNIKAL DAN PENYELENGGARAAN BAGI PERISIAN DAN APLIKASI SISTEM MAKLUMAT CONTINUING PROFESSIONAL DEVELOPMENT (MYCPD) KEMENTERIAN KESIHATAN MALAYSIA BAGI TEMPOH TIGA (3) TAHUN', code: 'QT250000000036597', category: 'IT Infrastructure', agency: 'BAHAGIAN KEWANGAN', oo: 'Siti Aisyah', staff: 'Muhammad Hafiz', avatarBg: '#3B78E0', deadline: '06 Jan 2026', daysLeft: 'Closed', created: '10 Mar 2026', urgent: false, value: 'RM 663,602.00', doc: 100, status: 'Done', publishDate: '24 Dec 2025', scope: 'Sokongan teknikal dan penyelenggaraan bagi perisian dan aplikasi sistem maklumat MyCPD KKM bagi tempoh 3 tahun.', notes: 'Tender closed, no award announcement received to date.' },
  { woNumber: '200-27012026-009', woDate: '2026-13-2', name: 'PERKHIDMATAN MEMBEKAL, MENGHANTAR, MEMASANG, MENGUJI, MENTAULIAH DAN MENYELENGGARA KOMPUTER MEJA, KOMPUTER RIBA, MESIN PENCETAK MONO, MESIN PENCETAK WARNA, MESIN PENCETAK MUDAH ALIH DAN MESIN PENGIMBAS SECARA SEWA GUNA SELAMA TIGA (3) TAHUN UNTUK KEGUNAAN KEMENTERIAN PERDAGANGAN DALAM NEGERI DAN KOS SARA HIDUP BAGI ZON 4 - MELAKA, NEGERI SEMBILAN DAN JOHOR.', code: 'QT250000000037047', category: 'IT Infrastructure', agency: 'Kementerian Perdagangan Dalam Negeri dan Kos Sara Hidup', oo: 'Ahmad Faizal', staff: 'Ahmad Faizal', avatarBg: 'var(--accent-solid)', deadline: '2/13/2026', daysLeft: 'Closed', created: '15 Jan 2026', urgent: false, value: 'RM 810,606.00', submitPrice: 'RM 883,741.00', doc: 100, status: 'Done', mode: 'EP', type: 'Tender', publishDate: '01 Jan 2026', hasBriefing: 'No', scope: 'PERKHIDMATAN MEMBEKAL, MENGHANTAR, MEMASANG, MENGUJI, MENTAULIAH DAN MENYELENGGARA KOMPUTER MEJA, KOMPUTER RIBA, MESIN PENCETAK MONO, MESIN ', notes: 'Tender closed and evaluated. Submission recorded.' },
  { woNumber: '200-27012026-011', woDate: '2026-13-2', name: 'PERKHIDMATAN MEMBEKAL, MENGHANTAR, MEMASANG, MENGUJI, MENTAULIAH DAN MENYELENGGARA KOMPUTER MEJA, KOMPUTER RIBA, MESIN PENCETAK MONO, MESIN PENCETAK WARNA, MESIN PENCETAK MUDAH ALIH DAN TABLET SECARA SEWA GUNA SELAMA TIGA (3) TAHUN UNTUK KEGUNAAN KEMENTERIAN PERDAGANGAN DALAM NEGERI DAN KOS SARA HIDUP BAGI ZON 2 - PERAK, SELANGOR DAN WILAYAH PERSEKUTUAN KUALA LUMPUR', code: 'QT250000000037043', category: 'IT Infrastructure', agency: 'Kementerian Perdagangan Dalam Negeri dan Kos Sara Hidup', oo: 'Nurul Ain', staff: 'Nurul Ain', avatarBg: 'var(--accent-solid)', deadline: '2/13/2026', daysLeft: 'Closed', created: '15 Jan 2026', urgent: false, value: 'RM 255,483.00', submitPrice: 'RM 248,065.00', doc: 100, status: 'Done', mode: 'EP', type: 'Tender', publishDate: '01 Jan 2026', hasBriefing: 'No', scope: 'PERKHIDMATAN MEMBEKAL, MENGHANTAR, MEMASANG, MENGUJI, MENTAULIAH DAN MENYELENGGARA KOMPUTER MEJA, KOMPUTER RIBA, MESIN PENCETAK MONO, MESIN ', notes: 'Tender closed and evaluated. Submission recorded.' },
  { woNumber: '200-27012026-010', woDate: '2026-13-2', name: 'PERKHIDMATAN MEMBEKAL, MENGHANTAR, MEMASANG, MENGUJI, MENTAULIAH DAN MENYELENGGARA KOMPUTER MEJA, KOMPUTER RIBA, MESIN PENCETAK MONO, MESIN PENCETAK WARNA, MESIN PENCETAK MUDAH ALIH DAN MESIN PENGIMBAS SECARA SEWA GUNA SELAMA TIGA (3) TAHUN UNTUK KEGUNAAN KEMENTERIAN PERDAGANGAN DALAM NEGERI DAN KOS SARA HIDUP BAGI ZON 3 - KELANTAN, TERENGGANU DAN PAHANG.', code: 'QT250000000037044', category: 'IT Infrastructure', agency: 'Kementerian Perdagangan Dalam Negeri dan Kos Sara Hidup', oo: 'Siti Aisyah', staff: 'Siti Aisyah', avatarBg: 'var(--warn-ink)', deadline: '2/13/2026', daysLeft: 'Closed', created: '15 Jan 2026', urgent: false, value: 'RM 486,597.00', submitPrice: 'RM 629,468.00', doc: 100, status: 'Done', mode: 'EP', type: 'Tender', publishDate: '01 Jan 2026', hasBriefing: 'No', scope: 'PERKHIDMATAN MEMBEKAL, MENGHANTAR, MEMASANG, MENGUJI, MENTAULIAH DAN MENYELENGGARA KOMPUTER MEJA, KOMPUTER RIBA, MESIN PENCETAK MONO, MESIN ', notes: 'Tender closed and evaluated. Submission recorded.' },
  { woNumber: '200-27012026-012', woDate: '2026-13-2', name: 'PERKHIDMATAN MEMBEKAL, MENGHANTAR, MEMASANG, MENGUJI, MENTAULIAH DAN MENYELENGGARA KOMPUTER MEJA, KOMPUTER RIBA, MESIN PENCETAK MONO, MESIN PENCETAK WARNA, MESIN PENCETAK MUDAH ALIH DAN MESIN PENGIMBAS SECARA SEWA GUNA SELAMA TIGA (3) TAHUN UNTUK KEGUNAAN KEMENTERIAN PERDAGANGAN DALAM NEGERI DAN KOS SARA HIDUP BAGI ZON 1 - PERLIS, KEDAH DAN PULAU PINANG.', code: 'QT250000000037038', category: 'IT Infrastructure', agency: 'Kementerian Perdagangan Dalam Negeri dan Kos Sara Hidup', oo: 'Muhammad Hafiz', staff: 'Muhammad Hafiz', avatarBg: '#3B78E0', deadline: '2/13/2026', daysLeft: 'Closed', created: '15 Jan 2026', urgent: false, value: 'RM 379,350.00', submitPrice: 'RM 855,109.00', doc: 100, status: 'Done', mode: 'EP', type: 'Tender', publishDate: '01 Jan 2026', hasBriefing: 'No', scope: 'PERKHIDMATAN MEMBEKAL, MENGHANTAR, MEMASANG, MENGUJI, MENTAULIAH DAN MENYELENGGARA KOMPUTER MEJA, KOMPUTER RIBA, MESIN PENCETAK MONO, MESIN ', notes: 'Tender closed and evaluated. Submission recorded.' },
  { woNumber: '200-27012026-003', woDate: '2026-15-2', name: 'TENDER PERKHIDMATAN SEWAAN PABX DI HOSPITAL RAJA PEREMPUAN ZAINAB II, KOTA BHARU BAGI TEMPOH TIGA (3) TAHUN', code: 'QT250000000036505', category: 'IT Infrastructure', agency: 'Hospital Raja Perempuan Zainab II, Kota Bharu', oo: 'Ahmad Faizal', staff: 'Ahmad Faizal', avatarBg: 'var(--accent-solid)', deadline: '2/15/2026', daysLeft: 'Closed', created: '15 Jan 2026', urgent: false, value: 'RM 530,623.00', submitPrice: 'RM 344,742.00', doc: 100, status: 'Done', mode: 'EP', type: 'Tender', publishDate: '01 Jan 2026', hasBriefing: 'No', scope: 'TENDER PERKHIDMATAN SEWAAN PABX DI HOSPITAL RAJA PEREMPUAN ZAINAB II, KOTA BHARU BAGI TEMPOH TIGA (3) TAHUN', notes: 'Tender closed and evaluated. Submission recorded.' },
  { woNumber: '200-27012026-005', woDate: '2026-16-2', name: 'MEMBEKAL, MENGHANTAR, MEMASANG, MENGKONFIGURASI, MENGUJI, MENTAULIAH, DAN KHIDMAT SOKONGAN BAGI INFRASTRUKTUR DATA BACKUP DAN DISASTER RECOVERY BACKUP DI BAHAGIAN PENGURUSAN MAKLUMAT, PEJABAT SETIAUSAHA KERAJAAN NEGERI SELANGOR', code: 'T/SUKSEL/05-2026', category: 'IT Infrastructure', agency: 'Pejabat Setiausaha Kerajaan Negeri Selangor', oo: 'Nurul Ain', staff: 'Nurul Ain', avatarBg: 'var(--accent-solid)', deadline: '2/16/2026', daysLeft: 'Closed', created: '15 Jan 2026', urgent: false, value: 'RM 676,560.00', submitPrice: 'RM 621,824.00', doc: 100, status: 'Done', mode: 'EP', type: 'Tender', publishDate: '01 Jan 2026', hasBriefing: 'No', scope: 'MEMBEKAL, MENGHANTAR, MEMASANG, MENGKONFIGURASI, MENGUJI, MENTAULIAH, DAN KHIDMAT SOKONGAN BAGI INFRASTRUKTUR DATA BACKUP DAN DISASTER RECOV', notes: 'Tender closed and evaluated. Submission recorded.' },
  { woNumber: '200-27012026-007', woDate: '2026-4-3', name: '[FTA (CPTPP)] PERKHIDMATAN MEMBEKAL, MENGHANTAR, MEMASANG, MENGUJI, MENTAULIAH DAN MENYELENGGARA KOMPUTER MEJA, KOMPUTER RIBA, MONITOR 24", MESIN PENCETAK MONO, MESIN PENCETAK WARNA, MESIN PENCETAK MUDAH ALIH, MESIN PENGIMBAS DAN TABLET SECARA SEWA GUNA SELAMA TIGA (3) TAHUN UNTUK KEGUNAAN KEMENTERIAN PERDAGANGAN DALAM NEGERI DAN KOS SARA HIDUP BAGI ZON 6 - IBU PEJABAT, WILAYAH PERSEKUTUAN PUTRAJAYA.', code: 'QT260000000000008', category: 'IT Infrastructure', agency: 'Kementerian Perdagangan Dalam Negeri dan Kos Sara Hidup', oo: 'Siti Aisyah', staff: 'Siti Aisyah', avatarBg: 'var(--warn-ink)', deadline: '3/4/2026', daysLeft: 'Closed', created: '15 Jan 2026', urgent: false, value: 'RM 153,672.00', submitPrice: 'RM 157,959.00', doc: 100, status: 'Done', mode: 'EP', type: 'Tender', publishDate: '01 Jan 2026', hasBriefing: 'No', scope: '[FTA (CPTPP)] PERKHIDMATAN MEMBEKAL, MENGHANTAR, MEMASANG, MENGUJI, MENTAULIAH DAN MENYELENGGARA KOMPUTER MEJA, KOMPUTER RIBA, MONITOR 24", ', notes: 'Tender closed and evaluated. Submission recorded.' },
  { woNumber: '200-27012026-002', woDate: '2026-9-3', name: '[FTA(CPTPP)] PERKHIDMATAN DAN LANGGANAN LESEN PERISIAN KESELAMATAN ICT ENDPOINT PROTECTION KEMENTERIAN PENDIDIKAN', code: 'QT250000000035057', category: 'IT Infrastructure', agency: 'Kementerian Pendidikan Malaysia', oo: 'Muhammad Hafiz', staff: 'Muhammad Hafiz', avatarBg: '#3B78E0', deadline: '3/9/2026', daysLeft: 'Closed', created: '15 Jan 2026', urgent: false, value: 'RM 573,287.00', submitPrice: 'RM 150,679.00', doc: 100, status: 'Done', mode: 'EP', type: 'Tender', publishDate: '01 Jan 2026', hasBriefing: 'No', scope: '[FTA(CPTPP)] PERKHIDMATAN DAN LANGGANAN LESEN PERISIAN KESELAMATAN ICT ENDPOINT PROTECTION KEMENTERIAN PENDIDIKAN', notes: 'Tender closed and evaluated. Submission recorded.' },
  { woNumber: '200-03022026-006', woDate: '2026-10-2', name: 'SEBUT HARGA PERKHIDMATAN PENYELENGGARAAN RANGKAIAN, SISTEM IP PABX DAN SOKONGAN PERALATAN IP PHONE ILSM, JABATAN PERANGKAAN MALAYSIA', code: 'QT250000000037022', category: 'IT Infrastructure', agency: 'Jabatan Perangkaan Malaysia', oo: 'Ahmad Faizal', staff: 'Ahmad Faizal', avatarBg: 'var(--accent-solid)', deadline: '2/10/2026', daysLeft: 'Closed', created: '15 Jan 2026', urgent: false, value: 'RM 749,324.00', submitPrice: 'RM 434,550.00', doc: 100, status: 'Done', mode: 'EP', type: 'Tender', publishDate: '01 Jan 2026', hasBriefing: 'No', scope: 'SEBUT HARGA PERKHIDMATAN PENYELENGGARAAN RANGKAIAN, SISTEM IP PABX DAN SOKONGAN PERALATAN IP PHONE ILSM, JABATAN PERANGKAAN MALAYSIA', notes: 'Tender closed and evaluated. Submission recorded.' },
  { woNumber: '200-03022026-007', woDate: '2026-24-2', name: 'MEMBEKAL, MEMASANG, MENGUJI DAN MENTAULIAH SISTEM TELEFON SERTA SISTEM IP PBX (PRIVATE BRANCH EXCHANGE) DI BILIK PABX UTAMA CANSELORI DI UNIVERSITI KEBANGSAAN MALAYSIA, BANGI.', code: 'NETST202600006', category: 'IT Infrastructure', agency: 'Universiti Kebangsaan Malaysia', oo: 'Nurul Ain', staff: 'Nurul Ain', avatarBg: 'var(--accent-solid)', deadline: '2/24/2026', daysLeft: 'Closed', created: '15 Jan 2026', urgent: false, value: 'RM 479,406.00', submitPrice: 'RM 725,624.00', doc: 100, status: 'Done', mode: 'EP', type: 'Tender', publishDate: '01 Jan 2026', hasBriefing: 'No', scope: 'MEMBEKAL, MEMASANG, MENGUJI DAN MENTAULIAH SISTEM TELEFON SERTA SISTEM IP PBX (PRIVATE BRANCH EXCHANGE) DI BILIK PABX UTAMA CANSELORI DI UNI', notes: 'Tender closed and evaluated. Submission recorded.' },
  { woNumber: '200-03022026-005', woDate: '2026-25-2', name: '[Hijau] Perolehan Membekal, Menghantar, Memasang, Menguji dan Mentauliah Peralatan Rangkaian bagi Ibu Pejabat dan Negeri/Cawangan Jabatan Kimia Malaysia.', code: 'QT260000000000275', category: 'IT Infrastructure', agency: 'Jabatan Kimia Malaysia', oo: 'Siti Aisyah', staff: 'Siti Aisyah', avatarBg: 'var(--warn-ink)', deadline: '2/25/2026', daysLeft: 'Closed', created: '15 Jan 2026', urgent: false, value: 'RM 729,307.00', submitPrice: 'RM 799,207.00', doc: 100, status: 'Done', mode: 'EP', type: 'Tender', publishDate: '01 Jan 2026', hasBriefing: 'No', scope: '[Hijau] Perolehan Membekal, Menghantar, Memasang, Menguji dan Mentauliah Peralatan Rangkaian bagi Ibu Pejabat dan Negeri/Cawangan Jabatan Ki', notes: 'Tender closed and evaluated. Submission recorded.' },
  { woNumber: '200-03022026-001', woDate: '2026-26-2', name: 'PERKHIDMATAN PEMBANGUNAN SISTEM PENGURUSAN PROGRAM LATIHAN KHIDMAT NEGARA (PLKN) 3.0 FASA 2 BAGI JABATAN LATIHAN KHIDMAT NEGARA (JLKN), KEMENTERIAN PERTAHANAN MALAYSIA', code: 'QT260000000001793', category: 'IT Infrastructure', agency: 'Jabatan Latihan Khidmat Negara, Kementerian Pertahanan', oo: 'Muhammad Hafiz', staff: 'Muhammad Hafiz', avatarBg: '#3B78E0', deadline: '2/26/2026', daysLeft: 'Closed', created: '15 Jan 2026', urgent: false, value: 'RM 220,722.00', submitPrice: 'RM 400,980.00', doc: 100, status: 'Done', mode: 'EP', type: 'Tender', publishDate: '01 Jan 2026', hasBriefing: 'No', scope: 'PERKHIDMATAN PEMBANGUNAN SISTEM PENGURUSAN PROGRAM LATIHAN KHIDMAT NEGARA (PLKN) 3.0 FASA 2 BAGI JABATAN LATIHAN KHIDMAT NEGARA (JLKN), KEME', notes: 'Tender closed and evaluated. Submission recorded.' },
  { woNumber: '200-03022026-004', woDate: '2026-27-2', name: 'PERKHIDMATAN SEWAAN PERALATAN ICT YANG MESRA ALAM JABATAN BOMBA DAN PENYELAMAT MALAYSIA NEGERI SABAH DAN AKADEMI BOMBA DAN PENYELAMAT WILAYAH SABAH UNTUK TEMPOH TIGA PULUH ENAM (36) BULAN', code: 'QT260000000000943', category: 'IT Infrastructure', agency: 'Jabatan Bomba dan Penyelamat Malaysia Sabah', oo: 'Ahmad Faizal', staff: 'Ahmad Faizal', avatarBg: 'var(--accent-solid)', deadline: '2/27/2026', daysLeft: 'Closed', created: '15 Jan 2026', urgent: false, value: 'RM 872,386.00', submitPrice: 'RM 491,436.00', doc: 100, status: 'Done', mode: 'EP', type: 'Tender', publishDate: '01 Jan 2026', hasBriefing: 'No', scope: 'PERKHIDMATAN SEWAAN PERALATAN ICT YANG MESRA ALAM JABATAN BOMBA DAN PENYELAMAT MALAYSIA NEGERI SABAH DAN AKADEMI BOMBA DAN PENYELAMAT WILAYA', notes: 'Tender closed and evaluated. Submission recorded.' },
  { woNumber: '200-03022026-003', woDate: '2026-5-3', name: 'TENDER PERKHIDMATAN SEWAAN PERALATAN ICT YANG MESRA ALAM JBPM LABUAN UNTUK TEMPOH 36 BULAN', code: 'QT260000000000579', category: 'IT Infrastructure', agency: 'Jabatan Bomba dan Penyelamat Malaysia Labuan', oo: 'Nurul Ain', staff: 'Nurul Ain', avatarBg: 'var(--accent-solid)', deadline: '3/5/2026', daysLeft: 'Closed', created: '15 Jan 2026', urgent: false, value: 'RM 514,516.00', submitPrice: 'RM 223,662.00', doc: 100, status: 'Done', mode: 'EP', type: 'Tender', publishDate: '01 Jan 2026', hasBriefing: 'No', scope: 'TENDER PERKHIDMATAN SEWAAN PERALATAN ICT YANG MESRA ALAM JBPM LABUAN UNTUK TEMPOH 36 BULAN', notes: 'Tender closed and evaluated. Submission recorded.' },
  { woNumber: '200-03022026-002', woDate: '2026-13-3', name: '[FTA(CPTPP)] Perolehan Membekal, Menghantar, Memasang, Menguji, Mengkonfigurasi dan Mentauliah Pelayan, Sistem Storan serta Pembangunan Repositori Data bagi Menyokong Analisis Data Raya (Big Data Analytics, BDA) dan Dashboard Pemantauan serta Pelaporan bagi Ibu Pejabat serta Negeri/Cawangan Jabatan Kimia Malaysia.', code: 'QT260000000000085', category: 'IT Infrastructure', agency: 'Jabatan Kimia Malaysia', oo: 'Siti Aisyah', staff: 'Siti Aisyah', avatarBg: 'var(--warn-ink)', deadline: '3/13/2026', daysLeft: 'Closed', created: '15 Jan 2026', urgent: false, value: 'RM 266,587.00', submitPrice: 'RM 854,447.00', doc: 100, status: 'Done', mode: 'EP', type: 'Tender', publishDate: '01 Jan 2026', hasBriefing: 'No', scope: '[FTA(CPTPP)] Perolehan Membekal, Menghantar, Memasang, Menguji, Mengkonfigurasi dan Mentauliah Pelayan, Sistem Storan serta Pembangunan Repo', notes: 'Tender closed and evaluated. Submission recorded.' },
  { woNumber: '200-04022026-003', woDate: '2026-11-2', name: 'MEMBEKAL, MENGHANTAR, MEMASANG, MENGUJILARI DAN MENTAULIAH PERALATAN CONFERENCE SYSTEM DI BILIK MESYUARAT AMETHYST, ARAS 3 EAST, AGENSI KELAYAKAN MALAYSIA (MQA)', code: 'QT260000000000085', category: 'IT Infrastructure', agency: 'Agensi Kelayakan Malaysia (MQA)', oo: 'Muhammad Hafiz', staff: 'Muhammad Hafiz', avatarBg: '#3B78E0', deadline: '2/11/2026', daysLeft: 'Closed', created: '15 Jan 2026', urgent: false, value: 'RM 848,445.00', submitPrice: 'RM 588,731.00', doc: 100, status: 'Done', mode: 'EP', type: 'Tender', publishDate: '01 Jan 2026', hasBriefing: 'No', scope: 'MEMBEKAL, MENGHANTAR, MEMASANG, MENGUJILARI DAN MENTAULIAH PERALATAN CONFERENCE SYSTEM DI BILIK MESYUARAT AMETHYST, ARAS 3 EAST, AGENSI KELA', notes: 'Tender closed and evaluated. Submission recorded.' },
  { name: 'Municipal Sports Complex Upgrade', code: 'QT250000000034999', category: 'Civil Works', agency: 'Majlis Perbandaran Klang', oo: 'Muhammad Hafiz', staff: 'Muhammad Hafiz', avatarBg: '#3B78E0', deadline: '15 Jul 2026', urgent: false, value: 'RM 274,749.00', submitPrice: 'RM 357,376.00', winPrice: 'RM 694,639.00', doc: 100, status: 'Lost', mode: 'EP', type: 'Tender', publishDate: '01 May 2026', hasBriefing: 'No', woNumber: '200-01052026-001', woDate: '01 May 2026', created: '01 May 2026', scope: 'Upgrade of municipal sports complex facilities.', notes: 'Not awarded — lost to a competing bidder.' },
  { name: 'District Office Data Center Cabling', code: 'QT250000000035112', category: 'IT Infrastructure', agency: 'Pejabat Daerah Kuantan', oo: 'Siti Aisyah', staff: 'Siti Aisyah', avatarBg: 'var(--warn-ink)', deadline: '20 Aug 2026', urgent: false, value: 'RM 402,754.00', submitPrice: 'RM 528,285.00', winPrice: 'RM 866,179.00', doc: 100, status: 'Lost', mode: 'EP', type: 'Tender', publishDate: '10 Jun 2026', hasBriefing: 'No', woNumber: '200-10062026-002', woDate: '10 Jun 2026', created: '10 Jun 2026', scope: 'Structured cabling works for district office data center.', notes: 'No award announcement received after 6 months — presumed lost.' },
  { name: 'PERKHIDMATAN MEMBEKAL, MENGHANTAR, MEMASANG, MENGUJI DAN MENTAULIAH PERALATAN ICT UNTUK MAKMAL KOMPUTER SEKOLAH BAGI ZON SARAWAK', code: 'QT250000000033810', category: 'IT Infrastructure', agency: 'Kementerian Pendidikan Malaysia', oo: 'Ahmad Faizal', staff: 'Ahmad Faizal', avatarBg: 'var(--accent-solid)', deadline: '10 Apr 2026', urgent: false, value: 'RM 753,415.00', submitPrice: 'RM 550,840.00', winPrice: 'RM 458,459.00', doc: 100, status: 'Lost', mode: 'EP', type: 'Tender', publishDate: '05 Mar 2026', hasBriefing: 'Yes', briefingDate: '15 Mar 2026', woNumber: '200-05032026-004', woDate: '05 Mar 2026', created: '05 Mar 2026', scope: 'Supply and installation of ICT lab equipment for schools in Sarawak zone.', notes: 'Lost — price uncompetitive against winning bidder.' },
  { name: 'SEBUT HARGA PERKHIDMATAN PENYELENGGARAAN SISTEM KAWALAN KESELAMATAN CCTV DI IBU PEJABAT POLIS KONTINJEN PERAK', code: 'SH250000000019233', category: 'IT Infrastructure', agency: 'Polis DiRaja Malaysia', oo: 'Nurul Ain', staff: 'Nurul Ain', avatarBg: 'var(--accent-solid)', deadline: '22 Feb 2026', urgent: false, value: 'RM 355,839.00', submitPrice: 'RM 733,683.00', winPrice: 'RM 588,407.00', doc: 100, status: 'Lost', mode: 'Non-EP', type: 'Quotation', publishDate: '01 Feb 2026', hasBriefing: 'No', woNumber: '200-01022026-005', woDate: '01 Feb 2026', created: '01 Feb 2026', scope: 'Maintenance of CCTV security control system at Perak police contingent HQ.', notes: 'Not selected — lost to incumbent vendor.' },
  { name: 'PERKHIDMATAN NAIK TARAF SISTEM PENYAMANAN UDARA (HVAC) DI BANGUNAN IBU PEJABAT KEMENTERIAN SUMBER MANUSIA', code: 'QT250000000034420', category: 'Civil Works', agency: 'Kementerian Sumber Manusia', oo: 'Muhammad Hafiz', staff: 'Ahmad Faizal', avatarBg: 'var(--accent-solid)', deadline: '18 May 2026', urgent: false, value: 'RM 145,055.00', submitPrice: 'RM 555,826.00', winPrice: 'RM 337,768.00', doc: 100, status: 'Lost', mode: 'EP', type: 'Tender', publishDate: '20 Apr 2026', hasBriefing: 'Yes', briefingDate: '28 Apr 2026', woNumber: '200-20042026-006', woDate: '20 Apr 2026', created: '20 Apr 2026', scope: 'Upgrading of HVAC system at Ministry of Human Resources HQ building.', notes: 'Bid unsuccessful — evaluation favoured another bidder on technical score.' },
].map((t, i) => ({ ...t, id: i }));

const ACTIVITY_LOG = [
  { text: 'Tender registered into the system', by: 'Sales Admin' },
  { text: 'Assigned to person in charge', by: 'Sales Manager' },
  { text: 'Borang ISI uploaded', by: 'PIC' },
  { text: 'Pricing schedule under review', by: 'Finance Team' },
];

class Component extends DCLogic {
  state = {
    view: 'dashboard', prevView: 'dashboard', selectedId: null, detailTab: 'overview', search: '', staffFilter: 'All',
    costingLines: null, costingSeq: 0, costingMeta: null,
    tendersData: RAW_TENDERS.slice(), nextTenderId: RAW_TENDERS.length,
    showRegisterModal: false,
    woSeq: 1,
    form: {
      mode: 'EP', pic: '', ministry: '', qtNo: '', qtTitle: '', publishDate: '',
      deadline: '', value: '', hasBriefing: 'No', briefingDate: '', type: 'Tender',
    },
    taskDocs: null, taskSeq: 100, newTaskName: '',
    viewingDoc: null,
    showMarkDoneConfirm: false, markDoneWarning: '',
    showBulkImport: false, bulkText: '',
    showBulkDocs: false, bulkDocsText: '',
    quotes: (() => { try { const q = JSON.parse(localStorage.getItem('tenderhub-quotes') || 'null'); return Array.isArray(q) ? normalizeQuotes(q) : QUOTE_SEED.map(x => ({ ...x, header: { ...QUOTE_TPL_DEFAULT.header }, terms: QUOTE_TPL_DEFAULT.terms })); } catch (e) { return QUOTE_SEED.map(x => ({ ...x, header: { ...QUOTE_TPL_DEFAULT.header }, terms: QUOTE_TPL_DEFAULT.terms })); } })(),
    quoteTpl: (() => { try { return JSON.parse(localStorage.getItem('tenderhub-quote-tpl') || 'null') || QUOTE_TPL_DEFAULT; } catch (e) { return QUOTE_TPL_DEFAULT; } })(),
    quoteId: null, quoteDateSort: 'desc', quoteTab: 'details', quoteSearch: '', quoteStatus: 'All', qTplMsg: '',
    pdStore: (() => { try { return JSON.parse(localStorage.getItem('tenderhub-pd') || '{}'); } catch (e) { return {}; } })(),
    sidebarCollapsed: false, mobileNavOpen: false, vw: typeof window !== 'undefined' ? window.innerWidth : 1280,
    ministrySearch: '', ministryDropdownOpen: false,
    openDropdown: null,
    page: 1,
    deadlineSortDir: 'desc',
  };
  PAGE_SIZE = 10;

  toggleSidebar() {
    if ((this.state.vw || 1280) < 1024) this.setState(s => ({ mobileNavOpen: !s.mobileNavOpen }));
    else this.setState(s => ({ sidebarCollapsed: !s.sidebarCollapsed }));
  }

  selectMinistry(m) {
    this.updateForm('ministry', m);
    this.setState({ ministrySearch: m, ministryDropdownOpen: false });
  }

  toggleDropdown(name) {
    this.setState(s => ({ openDropdown: s.openDropdown === name ? null : name }));
  }

  componentDidMount() {
    this._refs = {};
    this._onDocClick = (e) => {
      if (this.state.ministryDropdownOpen && this._ministryRef && !this._ministryRef.contains(e.target)) {
        this.setState({ ministryDropdownOpen: false });
      }
      if (this.state.openDropdown) {
        const ref = this._refs[this.state.openDropdown];
        if (ref && !ref.contains(e.target)) this.setState({ openDropdown: null });
      }
    };
    document.addEventListener('mousedown', this._onDocClick);
    this._initColResize();
    try { localStorage.setItem('tenderhub-quotes', JSON.stringify(this.state.quotes)); } catch (e) {}
    this._onResize = () => this.setState({ vw: window.innerWidth, ...(window.innerWidth >= 1024 ? { mobileNavOpen: false } : {}) });
    window.addEventListener('resize', this._onResize);
  }

  componentDidUpdate() {
    if (this.state.costingLines && this.state.costingSaved == null) this.setState({ costingSaved: JSON.stringify(this.state.costingLines) });
  }

  componentWillUnmount() {
    document.removeEventListener('mousedown', this._onDocClick);
    if (this._colObs) this._colObs.disconnect();
    window.removeEventListener('resize', this._onResize);
  }

  _initColResize() {
    const LS = 'tenderhub-colwidths';
    let saved = {};
    try { saved = JSON.parse(localStorage.getItem(LS) || '{}'); } catch (e) {}
    const keyOf = (table) => Array.from(table.querySelectorAll('thead th')).map(th => (th.dataset.colLabel ?? th.textContent.trim())).join('|');
    const applySaved = (table) => {
      const w = saved[keyOf(table)];
      if (!w) return;
      const ths = table.querySelectorAll('thead th');
      if (w.length !== ths.length) return;
      ths.forEach((th, i) => { th.style.width = w[i] + 'px'; });
      table.style.tableLayout = 'fixed';
      table.style.width = w.reduce((a, b) => a + b, 0) + 'px';
      table.style.minWidth = '0';
    };
    const freeze = (table) => {
      const ths = Array.from(table.querySelectorAll('thead th'));
      const widths = ths.map(th => th.getBoundingClientRect().width);
      ths.forEach((th, i) => { th.style.width = widths[i] + 'px'; });
      table.style.tableLayout = 'fixed';
      table.style.width = widths.reduce((a, b) => a + b, 0) + 'px';
      table.style.minWidth = '0';
      return ths;
    };
    const save = (table) => {
      const ths = Array.from(table.querySelectorAll('thead th'));
      saved[keyOf(table)] = ths.map(th => Math.round(th.getBoundingClientRect().width));
      try { localStorage.setItem(LS, JSON.stringify(saved)); } catch (e) {}
    };
    const attach = () => {
      document.querySelectorAll('table').forEach(table => {
        if (table.closest('[data-no-resize]')) return;
        const ths = table.querySelectorAll('thead th');
        if (!ths.length) return;
        let added = false;
        ths.forEach((th, i) => {
          if (th.querySelector(':scope > [data-col-resizer]')) return;
          if (!th.dataset.colLabel) th.dataset.colLabel = th.textContent.trim();
          if (getComputedStyle(th).position === 'static') th.style.position = 'relative';
          const h = document.createElement('div');
          h.setAttribute('data-col-resizer', '');
          h.title = 'Drag to resize · double-click to reset';
          h.style.cssText = 'position:absolute;top:0;right:-7px;width:14px;height:100%;cursor:col-resize;z-index:3;display:flex;justify-content:center;user-select:none;touch-action:none';
          const line = document.createElement('div');
          line.style.cssText = 'width:1px;height:50%;margin-top:auto;margin-bottom:auto;background:#E5E7EB;transition:background .12s,width .12s,height .12s';
          if (i === ths.length - 1) { line.style.opacity = '0'; h.style.right = '0'; h.style.justifyContent = 'flex-end'; }
          h.appendChild(line);
          const hi = (on) => { line.style.background = on ? '#8BE36B' : '#E5E7EB'; line.style.width = on ? '2px' : '1px'; line.style.height = on ? '100%' : '50%'; line.style.opacity = on || i !== ths.length - 1 ? '1' : '0'; };
          h.addEventListener('mouseenter', () => hi(true));
          h.addEventListener('mouseleave', () => { if (!h._drag) hi(false); });
          h.addEventListener('click', e => { e.stopPropagation(); e.preventDefault(); });
          h.addEventListener('dblclick', e => {
            e.stopPropagation();
            delete saved[keyOf(table)];
            try { localStorage.setItem(LS, JSON.stringify(saved)); } catch (err) {}
            table.querySelectorAll('thead th').forEach(t => { t.style.width = ''; });
            table.style.width = ''; table.style.tableLayout = ''; table.style.minWidth = '';
          });
          h.addEventListener('pointerdown', e => {
            e.preventDefault(); e.stopPropagation();
            const cols = freeze(table);
            const idx = cols.indexOf(th);
            const startX = e.clientX, startW = cols[idx].getBoundingClientRect().width;
            const others = cols.reduce((a, c, j) => j === idx ? a : a + c.getBoundingClientRect().width, 0);
            h._drag = true; hi(true);
            document.body.style.cursor = 'col-resize'; document.body.style.userSelect = 'none';
            const move = ev => {
              const w = Math.max(60, startW + ev.clientX - startX);
              th.style.width = w + 'px';
              table.style.width = (others + w) + 'px';
            };
            const up = () => {
              h._drag = false; hi(false);
              document.body.style.cursor = ''; document.body.style.userSelect = '';
              window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); window.removeEventListener('pointercancel', up);
              save(table);
            };
            window.addEventListener('pointermove', move); window.addEventListener('pointerup', up); window.addEventListener('pointercancel', up);
          });
          th.appendChild(h);
          added = true;
        });
        if (added) applySaved(table);
      });
    };
    attach();
    let raf = 0;
    this._colObs = new MutationObserver(() => { cancelAnimationFrame(raf); raf = requestAnimationFrame(attach); });
    this._colObs.observe(document.body, { childList: true, subtree: true });
  }

  openBulkImport() {
    this.setState({ showBulkImport: true, bulkText: '' });
  }

  closeBulkImport() {
    this.setState({ showBulkImport: false });
  }

  submitBulkImport() {
    const lines = this.state.bulkText.split('\n').map(l => l.trim()).filter(Boolean);
    const parsed = [];
    let seq = this.state.costingSeq;
    lines.forEach(line => {
      const parts = line.split(/\t|,/).map(p => p.trim());
      if (!parts[0]) return;
      const item = parts[0];
      const qty = Math.max(0, Number(parts[1]) || 1);
      const unit = parts[2] || 'unit';
      const unitCostRaw = Math.max(0, Number(String(parts[3] || '0').replace(/,/g, '')) || 0);
      parsed.push({ id: seq++, item, qty, unit, unitCostRaw, markupRaw: this.state.costingMeta.sellFactor, freq: 'One-off', months: 1, subItems: [] });
    });
    if (!parsed.length) return;
    this.setState(s => ({
      costingLines: [...s.costingLines, ...parsed],
      costingSeq: seq,
      showBulkImport: false,
    }));
  }

  openDocPreview(doc) {
    this.setState({ viewingDoc: doc });
  }

  closeDocPreview() {
    this.setState({ viewingDoc: null });
  }

  requestMarkDone() {
    const docs = this.state.taskDocs || [];
    const pending = docs.filter(d => !d.uploaded);
    if (pending.length > 0) {
      this.setState({ markDoneWarning: pending.length + ' document(s) still not uploaded: ' + pending.map(d => d.name).join(', ') + '. Please upload all documents before marking this tender as Done.' });
      return;
    }
    this.setState({ showMarkDoneConfirm: true, markDoneWarning: '' });
  }

  confirmMarkDone() {
    const id = this.state.selectedId;
    const rawLines = this.state.costingLines || [];
    const freqMult = (l) => l.months || 1;
    const lineCost = (l) => (l.subItems.length ? l.qty * l.subItems.reduce((s, si) => s + si.unitCostRaw, 0) : l.qty * l.unitCostRaw) * freqMult(l);
    const unitPrice = (l) => Math.ceil(((l.subItems.length ? l.subItems.reduce((s, si) => s + si.unitCostRaw, 0) : l.unitCostRaw) / (l.markupRaw || 1)) - 1e-9);
      const lineSell = (l) => unitPrice(l) * l.qty * freqMult(l);
    const totalSell = rawLines.reduce((sum, l) => sum + lineSell(l), 0);
    const totalSellFmt = 'RM ' + (Math.round((totalSell + Number.EPSILON) * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    this.setState(s => ({
      tendersData: s.tendersData.map(t => t.id !== id ? t : { ...t, status: 'Done', submitPrice: totalSellFmt }),
      view: 'done', prevView: 'done', selectedId: null,
      showMarkDoneConfirm: false,
    }));
  }

  openRegisterModal() {
    this.setState({
      showRegisterModal: true,
      form: { mode: 'EP', pic: '', oo: '', ministry: '', qtNo: '', qtTitle: '', publishDate: '', deadline: '', value: '', hasBriefing: 'No', briefingDate: '', type: 'Tender' },
      ministrySearch: '', ministryDropdownOpen: false,
    });
  }

  makeWoNumber() {
    const today = new Date();
    const dd = String(today.getDate()).padStart(2, '0');
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const yyyy = today.getFullYear();
    return { dateStr: dd + mm + yyyy, seqStr: String(this.state.woSeq).padStart(3, '0') };
  }

  closeRegisterModal() {
    this.setState({ showRegisterModal: false });
  }

  updateForm(field, value) {
    this.setState(s => ({ form: { ...s.form, [field]: value } }));
  }

  submitRegisterTender() {
    const f = this.state.form;
    if (!f.qtTitle.trim() || !f.qtNo.trim()) return;
    const wo = this.makeWoNumber();
    const woNumber = '200-' + wo.dateStr + '-' + wo.seqStr;
    const newTender = {
      id: this.state.nextTenderId,
      woNumber, woDate: new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }),
      mode: f.mode, type: f.type,
      name: f.qtTitle, code: f.qtNo,
      category: 'General', agency: f.ministry || '-',
      oo: f.oo || '-', staff: f.pic || '-',
      avatarBg: STAFF_COLORS[f.pic] || 'var(--muted)',
      publishDate: f.publishDate || '', deadline: f.deadline || 'TBC', daysLeft: '',
      created: new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }),
      hasBriefing: f.hasBriefing, briefingDate: f.hasBriefing === 'Yes' ? f.briefingDate : '',
      urgent: false, value: f.value ? ('RM ' + Number(f.value).toLocaleString('en-US')) : 'RM 0',
      doc: 0, status: 'In Progress',
      scope: '', notes: 'Newly registered tender.',
    };
    this.setState(s => ({
      tendersData: [...s.tendersData, newTender],
      nextTenderId: s.nextTenderId + 1,
      woSeq: s.woSeq + 1,
      showRegisterModal: false,
    }));
  }

  _costingKey(t) { return t ? (t.code || '') + '|' + t.id : ''; }
  _loadCostings() { try { return JSON.parse(localStorage.getItem('tenderhub-costing') || '{}'); } catch (e) { return {}; } }
  saveCosting() {
    const t = (this.state.tendersData || []).find(x => x.id === this.state.selectedId);
    const all = this._loadCostings();
    all[this._costingKey(t)] = { lines: this.state.costingLines, savedAt: new Date().toISOString() };
    try { localStorage.setItem('tenderhub-costing', JSON.stringify(all)); } catch (e) {}
    this.setState(s => ({ costingSaved: JSON.stringify(s.costingLines), costingSavedAt: new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) }));
  }

  costingFor(t, totalSell) {
    const stored = this._loadCostings()[this._costingKey(t)];
    if (stored && Array.isArray(stored.lines)) {
      let maxId = -1;
      stored.lines.forEach(l => { maxId = Math.max(maxId, l.id); (l.subItems || []).forEach(si => { maxId = Math.max(maxId, si.id); }); });
      return { lines: stored.lines, seq: maxId + 1, sellFactor: 0.80 };
    }
    if (t && t.costingPreset === 'skimRondaan') {
      const mk = 0.80;
      const lines = SKIM_RONDAAN_COSTING.map(([item, cost], i) => ({ id: i, item, qty: 1, unit: 'Unit', unitCostRaw: cost, markupRaw: mk, freq: 'One-off', months: 1, subItems: [] }));
      return { lines, seq: lines.length, sellFactor: mk };
    }
    return this.buildCostingLines(totalSell);
  }

  buildCostingLines(totalSell) {
    const totalCost = Math.round(totalSell * 0.80);
    const sellFactor = totalSell / totalCost;
    const hwTotal = Math.round(totalCost * 0.4545);
    const mk = 0.80;
    let seq = 0;
    const nextId = () => seq++;
    const lines = [
      {
        id: nextId(), item: 'Hardware & Equipment', qty: 20, unit: 'unit', unitCostRaw: 0, markupRaw: mk, freq: 'One-off', months: 1,
        subItems: [
          { id: nextId(), item: 'Desktop PC (Unit)', qty: 20, unit: 'unit', unitCostRaw: Math.round(hwTotal * 0.60 / 20), markupRaw: mk },
          { id: nextId(), item: 'Monitor', qty: 20, unit: 'unit', unitCostRaw: Math.round(hwTotal * 0.25 / 20), markupRaw: mk },
          { id: nextId(), item: 'Mouse & Keyboard Set', qty: 20, unit: 'unit', unitCostRaw: Math.round(hwTotal * 0.15 / 20), markupRaw: mk },
        ],
      },
      { id: nextId(), item: 'Installation & Labor', qty: 1, unit: 'lot', unitCostRaw: Math.round(totalCost * 0.3636), markupRaw: mk, freq: 'One-off', months: 1, subItems: [] },
      { id: nextId(), item: 'Licensing & Support', qty: 1, unit: 'lot', unitCostRaw: Math.round(totalCost * 0.1818), markupRaw: mk, freq: 'Monthly', months: 12, subItems: [] },
    ];
    return { lines, seq, sellFactor: mk };
  }

  addLineItem() {
    this.setState(s => ({
      costingLines: [...s.costingLines, { id: s.costingSeq, item: '', qty: 1, unit: 'lot', unitCostRaw: 0, markupRaw: s.costingMeta.sellFactor, freq: 'One-off', months: 1, subItems: [] }],
      costingSeq: s.costingSeq + 1,
    }));
  }

  addBreakdown(parentId) {
    this.setState(s => ({
      costingLines: s.costingLines.map(l => l.id !== parentId ? l : { ...l, subItems: [...l.subItems, { id: s.costingSeq, item: '', qty: 1, unit: 'unit', unitCostRaw: 0, markupRaw: s.costingMeta.sellFactor }] }),
      costingSeq: s.costingSeq + 1,
    }));
  }

  removeLineItem(id) {
    this.setState(s => ({ costingLines: s.costingLines.filter(l => l.id !== id) }));
  }

  addTaskDoc(name) {
    if (!name || !name.trim()) return;
    this.setState(s => ({
      taskDocs: [...s.taskDocs, {
        id: s.taskSeq, name: name.trim(), meta: 'Additional document', fileType: 'PDF', badgeBg: 'var(--bad-bg)', badgeColor: 'var(--bad-ink)',
        fileName: '', size: '', uploaded: false, uploadedDate: '', assignee: s.taskDocs[0] ? s.taskDocs[0].assignee : '',
      }],
      taskSeq: s.taskSeq + 1,
      newTaskName: '',
    }), () => this.persistDocs());
  }

  openBulkDocs() {
    this.setState({ showBulkDocs: true, bulkDocsText: '' });
  }

  closeBulkDocs() {
    this.setState({ showBulkDocs: false });
  }

  submitBulkDocs() {
    const names = this.state.bulkDocsText.split('\n').map(l => l.trim()).filter(Boolean);
    if (!names.length) return;
    let seq = this.state.taskSeq;
    const newDocs = names.map(name => ({
      id: seq++, name, meta: 'Additional document', fileType: 'PDF', badgeBg: 'var(--bad-bg)', badgeColor: 'var(--bad-ink)',
      fileName: '', size: '', uploaded: false, uploadedDate: '', assignee: this.state.taskDocs[0] ? this.state.taskDocs[0].assignee : '',
    }));
    this.setState(s => ({ taskDocs: [...s.taskDocs, ...newDocs], taskSeq: seq, showBulkDocs: false }), () => this.persistDocs());
  }

  quoteVals(isMobile, vw) {
    const inView = this.state.view === 'quotation';
    const f2 = (n) => n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const num = (x) => Number(String(x || '').replace(/,/g, '')) || 0;
    const fmtD = (d) => { const t = new Date(d); return isNaN(t) ? '-' : t.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }); };
    const addDays = (d, n) => { const t = new Date(d); if (isNaN(t)) return null; t.setDate(t.getDate() + (parseInt(n, 10) || 0)); return t; };
    const totals = (q) => { const sub = q.items.reduce((s, it) => s + num(it.qty) * num(it.price), 0); const sst = q.sst ? sub * num(q.sstRate) / 100 : 0; return { sub, sst, total: sub + sst }; };
    const tone = { Draft: ['var(--subtle-2)', 'var(--muted)'], Sent: ['var(--info-bg)', 'var(--info-ink)'], Accepted: ['var(--good-bg)', 'var(--good-ink)'], Rejected: ['var(--bad-bg)', 'var(--bad-ink)'], Expired: ['var(--warn-bg)', 'var(--warn-ink)'] };
    const today = new Date(); today.setHours(0, 0, 0, 0);
    const qq = this.state.quoteSearch.trim().toLowerCase();
    const statusOf = (q) => { const v = addDays(q.date, q.validity); return (q.status === 'Sent' || q.status === 'Draft') && v && v < today ? 'Expired' : q.status; };
    const all = this.state.quotes.map(q => ({ q, st: statusOf(q) }));
    const filtered = all.filter(({ q, st }) => (this.state.quoteStatus === 'All' || st === this.state.quoteStatus) && (!qq || [q.no, q.customer, q.subject].join(' ').toLowerCase().includes(qq)));
    const dir = this.state.quoteDateSort === 'asc' ? 1 : -1;
    filtered.sort((a, b) => ((new Date(a.q.date).getTime() || 0) - (new Date(b.q.date).getTime() || 0)) * dir || String(a.q.no).localeCompare(String(b.q.no)) * dir);
    const quoteRows = filtered.map(({ q, st }) => {
      const v = addDays(q.date, q.validity);
      return { no: q.no, dateFmt: fmtD(q.date), customer: q.customer || '(No customer)', subject: q.subject || '—', pic: q.pic,
        initials: (q.pic || '?').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase(), avatarBg: STAFF_COLORS[q.pic] || 'var(--muted)',
        totalFmt: 'RM ' + f2(totals(q).total), validFmt: v ? fmtD(v) : '-', validColor: st === 'Expired' ? 'var(--bad-ink)' : 'var(--muted)',
        status: st, statusBg: tone[st][0], statusColor: tone[st][1],
        onOpen: () => { this.setState({ quoteId: q.id, quoteTab: 'details', qTplMsg: '' }); this._migrateQuoteItems(q.id); } };
    });
    const chips = ['All', 'Draft', 'Sent', 'Accepted', 'Expired'].map(k => { const on = this.state.quoteStatus === k; return { label: k, weight: on ? 700 : 600, bg: on ? 'var(--chip)' : 'var(--surface)', color: on ? 'var(--chip-ink)' : '#6B7280', border: on ? 'var(--chip)' : 'var(--border-2)', onClick: () => this.setState({ quoteStatus: k }) }; });
    const cur = inView && this.state.quoteId != null ? this.state.quotes.find(x => x.id === this.state.quoteId) : null;
    const base = {
      isQuoteList: inView && !cur, isQuoteEditor: !!cur,
      quoteDateArrow: this.state.quoteDateSort === 'asc' ? '↑' : '↓',
      onToggleQuoteDateSort: () => this.setState(s => ({ quoteDateSort: s.quoteDateSort === 'asc' ? 'desc' : 'asc' })),
      quoteRows, quoteNoResults: inView && !cur && quoteRows.length === 0, quoteStatusChips: chips,
      quoteCountLabel: quoteRows.length + ' of ' + all.length + ' quotations',
      quoteSearch: this.state.quoteSearch, onQuoteSearch: (e) => this.setState({ quoteSearch: e.target.value }),
      onNewQuote: () => this.newQuote(),
      quoteGrid: 'minmax(0,1fr)', quoteFormPos: 'static', quotePaperPad: isMobile ? '12px' : '24px',
    };
    if (!cur) return { ...base, q: { header: {} }, qItems: [], qTermsList: [], qTotals: {}, qOn: {}, quoteTabs: [] };
    const t = totals(cur);
    const v = addDays(cur.date, cur.validity);
    const h = cur.header || QUOTE_TPL_DEFAULT.header;
    const set = (field) => (e) => this._patchQuote({ [field]: e.target.value });
    const setH = (field) => (e) => { const val = e.target.value; this._patchQuote(q => ({ ...q, header: { ...q.header, [field]: val } })); };
    const tabKey = this.state.quoteTab;
    const flash = (msg) => { this.setState({ qTplMsg: msg }); clearTimeout(this._qTplT); this._qTplT = setTimeout(() => this.setState({ qTplMsg: '' }), 2500); };
    const saveTpl = (patch, msg) => { const tpl = { ...this.state.quoteTpl, ...patch }; try { localStorage.setItem('tenderhub-quote-tpl', JSON.stringify(tpl)); } catch (e) {} this.setState({ quoteTpl: tpl }); flash(msg); };
    return { ...base,
      quoteTabs: [['details', 'Details'], ['items', 'Items'], ['header', 'Header'], ['terms', 'Terms & Conditions'], ['preview', 'Preview']].map(([k, label]) => ({ label, onClick: () => this.setState({ quoteTab: k, qTplMsg: '' }), weight: tabKey === k ? 700 : 600, bg: tabKey === k ? 'var(--accent)' : 'transparent', color: tabKey === k ? 'var(--accent-ink)' : '#6B7280' })),
      qPreviewDisplay: tabKey === 'preview' ? 'block' : 'none', qFormBodyDisplay: tabKey === 'preview' ? 'none' : 'flex',
      qTabDetails: tabKey === 'details', qTabItems: tabKey === 'items', qTabHeader: tabKey === 'header', qTabTerms: tabKey === 'terms',
      qTplMsg: this.state.qTplMsg,
      quotePicOptions: Object.keys(STAFF_COLORS),
      q: { picPhone: '', picEmail: '', attnPhone: '', attnEmail: '', ...cur, showSig: cur.sigOn !== false, showStampImg: cur.stampOn !== false && !!h.stamp, dateFmt: fmtD(cur.date), validFmt: v ? fmtD(v) : '-', customerOr: cur.customer || 'Customer name', subjectOr: cur.subject || '—',
        header: { ...h, initial: (h.company || '?').trim()[0] || '?', regNoLabel: h.regNo ? '(' + h.regNo + ')' : '', contactLine: [h.phone && 'Tel: ' + h.phone, h.email, h.website].filter(Boolean).join('  ·  ') } },
      qItems: cur.items.map((it, i) => {
        const upd = (field) => (e) => { const val = e.target.value; this._patchQuote(q => ({ ...q, items: q.items.map((x, j) => j !== i ? x : { ...x, [field]: field === 'qty' || field === 'price' ? val.replace(/[^0-9.,]/g, '') : val }) })); };
        const nit = normalizeQuoteItem(it);
        const title = nit.title || '';
        const specs = nit.specs || '';
        const specList = specs.split('\n').map(x => x.trim()).filter(Boolean).map(line => { const k = line.indexOf(':'); return k > 0 && k < 40 ? { label: line.slice(0, k + 1), value: line.slice(k + 1) } : { label: '', value: line }; });
        return { ...it, title, specs, specList, specRows: Math.min(8, Math.max(2, specs.split('\n').length)), titleOr: title || '—', n: i + 1,
          onTitle: upd('title'), onSpecs: upd('specs'), priceFmt: f2(num(it.price)), amountFmt: f2(num(it.qty) * num(it.price)),
          onDesc: upd('desc'), onQty: upd('qty'), onUnit: upd('unit'), onPrice: upd('price'),
          onRemove: () => this._patchQuote(q => ({ ...q, items: q.items.filter((_, j) => j !== i) })) };
      }),
      qTotals: { subtotal: f2(t.sub), sst: f2(t.sst), total: f2(t.total), words: amountInWords(Math.round(t.total * 100) / 100) },
      qTermsList: (cur.terms || '').split('\n').map(x => x.trim()).filter(Boolean).map((text, i) => ({ n: i + 1, text })),
      qSstTrack: cur.sst ? 'var(--accent)' : 'var(--border-2)', qSstKnob: cur.sst ? '16px' : '2px',
      qSigTrack: cur.sigOn !== false ? 'var(--accent)' : 'var(--border-2)', qSigKnob: cur.sigOn !== false ? '16px' : '2px',
      qStampTrack: cur.stampOn !== false ? 'var(--accent)' : 'var(--border-2)', qStampKnob: cur.stampOn !== false ? '16px' : '2px',
      qStampMissing: cur.stampOn !== false && !h.stamp, qNoStamp: !h.stamp, qStampBg: h.stamp ? 'url("' + h.stamp + '")' : 'none',
      qOn: { status: set('status'), date: set('date'), validity: set('validity'), customer: set('customer'), attn: set('attn'), custAddress: set('custAddress'), subject: set('subject'), pic: set('pic'), picTitle: set('picTitle'), picPhone: set('picPhone'), attnPhone: set('attnPhone'), attnEmail: set('attnEmail'), picEmail: set('picEmail'), terms: set('terms'), sstRate: set('sstRate'),
        toggleSst: () => this._patchQuote(q => ({ ...q, sst: !q.sst })),
        toggleSig: () => this._patchQuote(q => ({ ...q, sigOn: q.sigOn === false })),
        toggleStamp: () => this._patchQuote(q => ({ ...q, stampOn: q.stampOn === false })),
        stampFile: (e) => { const f = e.target.files && e.target.files[0]; if (!f) return; const r = new FileReader(); r.onload = () => this._patchQuote(q => ({ ...q, header: { ...q.header, stamp: r.result } })); r.readAsDataURL(f); e.target.value = ''; },
        removeStamp: () => this._patchQuote(q => ({ ...q, header: { ...q.header, stamp: '' } })),
        hCompany: setH('company'), hRegNo: setH('regNo'), hSstNo: setH('sstNo'), hAddress: setH('address'), hPhone: setH('phone'), hEmail: setH('email'), hWebsite: setH('website') },
      onAddQuoteItem: () => this._patchQuote(q => ({ ...q, items: [...q.items, { title: '', specs: '', qty: '1', unit: 'Unit', price: '' }] })),
      onQuoteBack: () => this.setState({ quoteId: null }),
      onDuplicateQuote: () => this.newQuote(cur),
      onPrintQuote: () => this.printQuote(),
      quotePreviewRef: (el) => { this._quotePreview = el; },
      onSaveHeaderDefault: () => saveTpl({ header: { ...cur.header } }, 'Saved — new quotations will use this header.'),
      onResetHeader: () => { this._patchQuote({ header: { ...this.state.quoteTpl.header } }); flash('Default header applied.'); },
      onSaveTermsDefault: () => saveTpl({ terms: cur.terms }, 'Saved — new quotations will use these terms.'),
      onResetTerms: () => { this._patchQuote({ terms: this.state.quoteTpl.terms }); flash('Default terms applied.'); },
    };
  }

  _migrateQuoteItems(id) {
    const q = this.state.quotes.find(x => x.id === id);
    if (!q || !q.items.some(it => it.desc != null)) return;
    this._setQuotes(qs => qs.map(x => x.id !== id ? x : { ...x, items: x.items.map(normalizeQuoteItem) }));
  }

  _setQuotes(fn, extra) {
    this.setState(s => {
      const quotes = fn(s.quotes);
      try { localStorage.setItem('tenderhub-quotes', JSON.stringify(quotes)); } catch (e) {}
      return { quotes, ...(extra || {}) };
    });
  }
  _patchQuote(patch) {
    const id = this.state.quoteId;
    this._setQuotes(qs => qs.map(q => q.id !== id ? q : (typeof patch === 'function' ? patch(q) : { ...q, ...patch })));
  }
  newQuote(from) {
    const year = new Date().getFullYear();
    const maxSeq = this.state.quotes.reduce((m, q) => { const mm = /-(\d+)$/.exec(q.no || ''); return Math.max(m, mm ? parseInt(mm[1], 10) : 0); }, 0);
    const id = Date.now();
    const tpl = this.state.quoteTpl;
    const base = from ? JSON.parse(JSON.stringify(from)) : { customer: '', attn: '', custAddress: '', subject: '', pic: 'Siti Aisyah', picTitle: 'Sales Executive', picPhone: '', picEmail: '', sst: true, sstRate: '8', items: [{ title: '', specs: '', qty: '1', unit: 'Unit', price: '' }], header: { ...tpl.header }, terms: tpl.terms };
    const q = { ...base, id, no: 'QTN-' + year + '-' + String(maxSeq + 1).padStart(4, '0'), date: new Date().toISOString().slice(0, 10), validity: base.validity || '30', status: 'Draft' };
    this._setQuotes(qs => [q, ...qs], { quoteId: id, quoteTab: 'details', qTplMsg: '' });
  }
  printQuote() {
    const el = this._quotePreview; if (!el) return;
    const q = this.state.quotes.find(x => x.id === this.state.quoteId);
    const w = window.open('', '_blank'); if (!w) return;
    w.document.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + (q ? q.no : 'Quotation') + '</title><link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600&display=swap" rel="stylesheet"><style>@page{size:A4;margin:12mm}body{margin:0;-webkit-print-color-adjust:exact;print-color-adjust:exact}</style></head><body>' + (() => { const c = el.cloneNode(true); c.querySelectorAll('[data-col-resizer]').forEach(n => n.remove()); return c.outerHTML; })().replace('box-shadow', 'x-shadow') + '</body></html>');
    w.document.close(); w.focus(); setTimeout(() => w.print(), 900);
  }

  _pdKey() {
    const t = (this.state.tendersData || []).find(x => x.id === this.state.selectedId);
    return this._costingKey(t);
  }
  _pdMutate(fn) {
    this.setState(s => {
      const key = this._pdKey();
      const cat = s.pdCat || 'collection';
      const store = { ...(s.pdStore || {}) };
      const tender = { ...(store[key] || {}) };
      tender[cat] = fn(tender[cat] || []);
      store[key] = tender;
      try { localStorage.setItem('tenderhub-pd', JSON.stringify(store)); } catch (e) {}
      return { pdStore: store };
    });
  }
  addPdLine() {
    const id = Date.now();
    const cat = this.state.pdCat || 'collection';
    const base = cat === 'collection' ? { id, name: '', note: '', date: '', budget: '' }
      : ['principal', 'distributor', 'partner'].includes(cat) ? { id, name: '', ref: '', budget: '', pr: '', po: '', inv: '', paid: '' }
      : { id, name: '', ref: '', budget: '', actual: '' };
    this._pdMutate(rows => [...rows, base]);
  }
  updatePdLine(id, field, value) {
    const numeric = !['name', 'ref', 'note', 'date'].includes(field);
    const v = numeric ? String(value).replace(/[^0-9.,]/g, '') : value;
    this._pdMutate(rows => rows.map(r => r.id === id ? { ...r, [field]: v } : r));
  }
  removePdLine(id) { this._pdMutate(rows => rows.filter(r => r.id !== id)); }

  docsFor(t, defaults) {
    let all = {};
    try { all = JSON.parse(localStorage.getItem('tenderhub-docs') || '{}'); } catch (e) {}
    const stored = all[this._costingKey(t)];
    if (Array.isArray(stored)) return { taskDocs: stored, taskSeq: stored.reduce((m, d) => Math.max(m, d.id), -1) + 1 };
    return { taskDocs: defaults };
  }

  persistDocs() {
    const t = (this.state.tendersData || []).find(x => x.id === this.state.selectedId);
    if (!t || !this.state.taskDocs) return;
    let all = {};
    try { all = JSON.parse(localStorage.getItem('tenderhub-docs') || '{}'); } catch (e) {}
    all[this._costingKey(t)] = this.state.taskDocs;
    try { localStorage.setItem('tenderhub-docs', JSON.stringify(all)); } catch (e) {}
    this.setState({ docsSavedAt: new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) });
  }

  toggleTaskDoc(id) {
    const today = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    this.setState(s => ({ taskDocs: s.taskDocs.map(d => d.id !== id ? d : { ...d, uploaded: !d.uploaded, uploadedDate: !d.uploaded ? today : '', doneBy: !d.uploaded ? 'Siti Aisyah' : '' }) }), () => this.persistDocs());
  }

  handleFileUpload(id, file) {
    if (!file) return;
    const today = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    const sizeStr = file.size > 1024 * 1024 ? (file.size / (1024 * 1024)).toFixed(1) + ' MB' : (file.size / 1024).toFixed(0) + ' KB';
    const ext = (file.name.split('.').pop() || '').toLowerCase();
    const fileType = ext === 'xlsx' || ext === 'xls' ? 'XLS' : ext === 'docx' || ext === 'doc' ? 'DOC' : 'PDF';
    const badge = FILE_BADGE[fileType] || FILE_BADGE.PDF;
    this.setState(s => ({
      taskDocs: s.taskDocs.map(d => d.id !== id ? d : {
        ...d, uploaded: true, uploadedDate: today, fileName: file.name, size: sizeStr, fileType, badgeBg: badge.bg, badgeColor: badge.color,
      }),
    }));
  }


  removeSubItem(parentId, subId) {
    this.setState(s => ({ costingLines: s.costingLines.map(l => l.id !== parentId ? l : { ...l, subItems: l.subItems.filter(si => si.id !== subId) }) }));
  }

  updateLineField(id, field, value) {
    let v;
    if (field === 'item' || field === 'unit' || field === 'freq' || field === 'vendor' || field === 'quoteUrl') v = value;
    else if (field === 'months') v = Math.max(1, parseInt(String(value).replace(/[^0-9]/g, ''), 10) || 1);
    else v = Math.max(0, Number(String(value).replace(/,/g, '')) || 0);
    this.setState(s => ({ costingLines: s.costingLines.map(l => l.id !== id ? l : { ...l, [field]: v }) }));
  }

  _vendorMenuVals(opts) {
    const vm = this.state.vendorMenu;
    if (!vm) return { vendorMenuOpen: false, vendorMenuItems: [] };
    const q = (vm.q || '').trim(), ql = q.toLowerCase();
    const list = vm.all || !ql ? opts : opts.filter(o => o.toLowerCase().includes(ql));
    this._vmList = list; this._vmQ = q;
    const pick = (v) => { vm.set(v); this.setState({ vendorMenu: null }); };
    this._vmPick = pick;
    const cur = (vm.cur !== undefined ? vm.cur : null);
    return {
      vendorMenuOpen: true, vendorMenuTop: vm.top + 'px', vendorMenuLeft: vm.left + 'px', vendorMenuWidth: vm.width + 'px',
      vendorQuery: q,
      vendorMenuItems: list.map((name, i) => ({ name, initial: name.replace(/[^A-Za-z0-9]/g, '').slice(0, 1).toUpperCase(), selected: name === q && vm.all, weight: name === q ? 700 : 500, bg: i === vm.hi ? 'var(--hover)' : 'transparent', onPick: () => pick(name) })),
      vendorMenuCanAdd: !!q && !opts.some(o => o.toLowerCase() === ql),
      vendorMenuEmpty: !list.length && !q,
      onVendorAdd: () => pick(q),
      onMenuMouseDown: (e) => { e.preventDefault(); },
    };
  }
  _vendorKey(e) {
    const vm = this.state.vendorMenu; if (!vm) return;
    const n = (this._vmList || []).length;
    if (e.key === 'ArrowDown') { e.preventDefault(); this.setState({ vendorMenu: { ...vm, hi: Math.min(n - 1, vm.hi + 1) } }); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); this.setState({ vendorMenu: { ...vm, hi: Math.max(0, vm.hi - 1) } }); }
    else if (e.key === 'Enter') { e.preventDefault(); const v = vm.hi >= 0 && this._vmList[vm.hi] ? this._vmList[vm.hi] : this._vmQ; this._vmPick(v); e.target.blur(); }
    else if (e.key === 'Escape') { this.setState({ vendorMenu: null }); e.target.blur(); }
  }
  updateSubField(parentId, subId, field, value) {
    const v = (field === 'item' || field === 'unit' || field === 'vendor' || field === 'quoteUrl') ? value : Math.max(0, Number(String(value).replace(/,/g, '')) || 0);
    this.setState(s => ({ costingLines: s.costingLines.map(l => l.id !== parentId ? l : { ...l, subItems: l.subItems.map(si => si.id !== subId ? si : { ...si, [field]: v }) }) }));
  }

  renderVals() {
    const h = (svg) => ({ __html: svg });
    const navConfig = [
      { key: 'dashboard', label: 'Dashboard', icon: h(ICONS.dashboard) },
      { key: 'inprogress', label: 'In Progress', icon: h(ICONS.clock) },
      { key: 'quotation', label: 'Quotation', icon: h(ICONS.quotation) },
      { key: 'done', label: 'Done', icon: h(ICONS.check) },
      { key: 'awarded', label: 'Awarded', icon: h(ICONS.award) },
      { key: 'lost', label: 'Lost', icon: h(ICONS.x) },
      { key: 'performance', label: 'Status', icon: h(ICONS.staff) },
      { key: 'settings', label: 'Settings', icon: h(ICONS.settings) },
    ];
    const vw = this.state.vw || 1280;
    const isDrawer = vw < 1024, isMobile = vw < 640, isTablet = vw < 900;
    const collapsed = isDrawer ? false : this.state.sidebarCollapsed;
    const navByKey = {};
    navConfig.forEach(n => { navByKey[n.key] = n; });
    const navItem = (key, badge) => {
      const n = navByKey[key];
      const active = this.state.view === key;
      const hovered = this.state.navHover === key;
      return {
        label: n.label, icon: n.icon,
        onEnter: () => this.setState({ navHover: key }),
        onLeave: () => this.setState({ navHover: null }),
        bg: active ? 'var(--accent)' : hovered ? 'var(--border)' : 'transparent',
        color: active ? 'var(--accent-ink)' : hovered ? 'var(--ink)' : '#6B7280',
        weight: active ? 700 : 600,
        onClick: () => this.setState({ view: key, search: '', staffFilter: 'All', page: 1, mobileNavOpen: false, quoteId: null }),
        labelDisplay: collapsed ? 'none' : 'block',
        pad: collapsed ? '8px' : '8px 10px',
        iconColor: active ? 'var(--accent-ink)' : '#6B7280',
        justify: collapsed ? 'center' : 'flex-start',
        badge: badge || '',
        badgeDisplay: badge && !collapsed ? 'inline-flex' : 'none',
        badgeBg: active ? 'rgba(22,51,11,0.14)' : 'var(--hover)',
        badgeColor: active ? 'var(--accent-ink)' : 'var(--muted)',
      };
    };
    const navGroupSpec = [
      { label: 'Operations', keys: ['dashboard', 'inprogress'] },
      { label: 'Pipeline', keys: ['done', 'awarded', 'lost'] },
      { label: 'Simple Quotation', keys: ['quotation'] },
      { label: 'Insights', keys: ['performance', 'settings'] },
    ];

    const tendersData = this.state.tendersData;
    const counts = { 'In Progress': 0, 'Awarded': 0, 'Done': 0, 'Lost': 0 };
    tendersData.forEach(t => counts[t.status]++);

    const navGroups = navGroupSpec.map(g => ({
      label: collapsed ? '' : g.label,
      labelPad: collapsed ? '0' : '6px 10px',
      labelHeight: collapsed ? '8px' : 'auto',
      items: g.keys.map(k => navItem(k, counts[{ inprogress: 'In Progress', done: 'Done', awarded: 'Awarded', lost: 'Lost' }[k]])),
    }));

    const toNum = (v) => Number(String(v || '0').replace(/[^0-9.]/g, '')) || 0;
    const closedCount = counts['Awarded'] + counts['Done'] + (counts['Lost'] || 0);
    const winRate = closedCount ? Math.round((counts['Awarded'] / closedCount) * 100) : 0;
    const totalValue = tendersData.reduce((s, t) => s + toNum(t.value), 0);
    const fmtRM = (n) => 'RM ' + (n >= 1000000 ? (n / 1000000).toFixed(1) + 'M' : n.toLocaleString('en-US'));

    const activeFilterCount = [
      this.state.staffFilter && this.state.staffFilter !== 'All',
      this.state.agencyFilter && this.state.agencyFilter !== 'All',
      this.state.modeFilter && this.state.modeFilter !== 'All',
      !!this.state.dateFrom, !!this.state.dateTo, !!this.state.mineOnly,
    ].filter(Boolean).length;


    const WORKLOAD_PASTELS = ['#A7C7F2', '#F6C9A8', '#B5E0C4', '#D9BFF0', '#F2B8C6', '#F5DE9B', '#A9DDE0'];
    const staffWorkload = Object.keys(STAFF_COLORS).map(name => {
      const owned = tendersData.filter(t => t.staff === name);
      const val = owned.reduce((s, t) => s + toNum(t.value), 0);
      return { name, initials: name.split(' ').map(x => x[0]).join('').slice(0, 2).toUpperCase(), count: owned.length, value: fmtRM(val), avatarBg: STAFF_COLORS[name] };
    }).sort((a, b) => b.count - a.count);
    const maxWorkload = Math.max(1, ...staffWorkload.map(w => w.count));
    const MONS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const woMonthKey = (t) => {
      const m1 = String(t.woNumber || '').match(/-(\d{2})(\d{2})(\d{4})-/);
      if (m1) return m1[3] + '-' + m1[2];
      const d = String(t.woDate || '').trim();
      let m = d.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
      if (m) { const mo = +m[3] <= 12 && +m[2] > 12 ? m[3] : m[2]; return m[1] + '-' + String(mo).padStart(2, '0'); }
      m = d.match(/^(\d{1,2})\s+([A-Za-z]{3})\w*\s+(\d{4})$/);
      if (m) { const i = MONS.findIndex(x => x.toLowerCase() === m[2].toLowerCase()); if (i >= 0) return m[3] + '-' + String(i + 1).padStart(2, '0'); }
      return '';
    };
    const picSumMonth = this.state.picSumMonth || 'all';
    const monthKeys = [...new Set(tendersData.map(woMonthKey).filter(Boolean))].sort().reverse();
    const picSumMonths = [{ value: 'all', label: '(All)' }, ...monthKeys.map(k => ({ value: k, label: MONS[+k.slice(5) - 1] + ' ' + k.slice(0, 4) }))];
    const picScope = picSumMonth === 'all' ? tendersData : tendersData.filter(t => woMonthKey(t) === picSumMonth);
    const dCounts = { 'In Progress': 0, 'Awarded': 0, 'Done': 0, 'Lost': 0 };
    picScope.forEach(t => { if (t.status in dCounts) dCounts[t.status]++; });
    const dClosed = dCounts['Awarded'] + dCounts['Done'] + dCounts['Lost'];
    const dWin = dClosed ? Math.round(dCounts['Awarded'] / dClosed * 100) : 0;
    const dTotal = picScope.reduce((a, t) => a + toNum(t.value), 0);
    const dWon = picScope.filter(t => t.status === 'Awarded').reduce((a, t) => a + toNum(t.value), 0);
    const kpis = [
      { label: 'In Progress', value: dCounts['In Progress'], delta: '4 due this week', deltaBg: 'var(--warn-bg)', deltaInk: 'var(--warn-ink)', icon: h(ICONS.clock) },
      { label: 'Awarded', value: dCounts['Awarded'], delta: fmtRM(dWon) + ' won', deltaBg: 'var(--good-bg)', deltaInk: 'var(--good-ink)', icon: h(ICONS.award) },
      { label: 'Done', value: dCounts['Done'], delta: 'Archived', deltaBg: 'var(--hover)', deltaInk: 'var(--muted)', icon: h(ICONS.check) },
      { label: 'Lost', value: dCounts['Lost'] || 0, delta: 'Reviewed', deltaBg: 'var(--bad-bg)', deltaInk: 'var(--bad-ink)', icon: h(ICONS.x) },
      { label: 'Win rate', value: dWin + '%', delta: 'Of closed bids', deltaBg: 'var(--good-bg)', deltaInk: 'var(--good-ink)', icon: h('<svg viewBox="0 0 24 24" fill="none" width="100%" height="100%" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V5"/><path d="M4 19h16"/><path d="M8 15l4-5 3 3 4-6"/></svg>') },
      { label: 'Portfolio value', value: fmtRM(dTotal), delta: '', deltaBg: 'var(--hover)', deltaInk: 'var(--muted)', icon: h(ICONS.tenders) },
    ].map((k) => ({ ...k, valueSize: '20px', deltaDisplay: k.delta ? 'inline' : 'none' }));

    const statusDonutColors = { 'In Progress': '#A7C7F2', 'Awarded': '#B5E0C4', 'Done': '#CFCBDD', 'Lost': '#F2B8C6' };
    const statusOrder = ['In Progress', 'Awarded', 'Done', 'Lost'];
    let acc = 0;
    const donutSegments = statusOrder.map(st => {
      const pct = picScope.length ? (dCounts[st] || 0) / picScope.length * 100 : 0;
      const seg = `${statusDonutColors[st]} ${acc}% ${acc + pct}%`;
      acc += pct;
      return seg;
    });
    const donutGradient = `conic-gradient(${donutSegments.join(', ')})`;
    const donutLegend = statusOrder.map(st => ({
      label: st, count: dCounts[st] || 0, color: statusDonutColors[st],
      pct: picScope.length ? Math.round((dCounts[st] || 0) / picScope.length * 100) : 0,
    }));
    const f2d = (n) => n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const picAgg = {};
    picScope.forEach(t => { const k = (t.staff || '').trim() || '(blank)'; picAgg[k] = picAgg[k] || { count: 0, sum: 0 }; picAgg[k].count++; picAgg[k].sum += toNum(t.value); });
    const picSumRows = Object.entries(picAgg).sort((a, b) => a[0] === '(blank)' ? 1 : b[0] === '(blank)' ? -1 : a[0].localeCompare(b[0]))
      .map(([name, v]) => ({ name, count: v.count, sum: v.sum ? f2d(v.sum) : '', _v: v.sum }));
    const picMax = Math.max(1, ...picSumRows.map(r => r._v));
    const swMap = Object.fromEntries(staffWorkload.map(w => [w.name, w]));
    picSumRows.forEach(r => { const w = swMap[r.name]; r.initials = w ? w.initials : r.name === '(blank)' ? '–' : r.name.split(/\s+/).map(x => x[0]).slice(0, 2).join('').toUpperCase(); r.avatarBg = w ? w.avatarBg : 'var(--muted-2)'; r.barPct = (r._v / picMax * 100).toFixed(1) + '%'; });
    const STATUS_ORDER = ['In Progress', 'Done', 'Awarded', 'Lost'];
    const modeStat = (label, list) => {
      const st = [...STATUS_ORDER, ...[...new Set(list.map(t => t.status))].filter(x => x && !STATUS_ORDER.includes(x))];
      return { label, count: list.length, share: picScope.length ? Math.round(list.length / picScope.length * 100) + '%' : '0%',
        value: 'RM ' + f2d(list.reduce((a, t) => a + toNum(t.value), 0)), statuses: st.map(x => ({ label: x, count: list.filter(t => t.status === x).length })) };
    };
    const epList = picScope.filter(t => String(t.mode || '').toUpperCase() === 'EP');
    const epStat = modeStat('EP', epList), nonEpStat = modeStat('Non-EP', picScope.filter(t => String(t.mode || '').toUpperCase() !== 'EP'));
    const epBarPct = picScope.length ? (epList.length / picScope.length * 100).toFixed(1) + '%' : '0%';
    const picSumTotalCount = picScope.length, picSumTotal = f2d(picScope.reduce((s, t) => s + toNum(t.value), 0));
    staffWorkload.forEach((w, i) => { w.barPct = (w.count / maxWorkload * 100) + '%'; w.barColor = WORKLOAD_PASTELS[i % WORKLOAD_PASTELS.length]; });

    const agencyTotals = {};
    tendersData.forEach(t => { agencyTotals[t.agency] = (agencyTotals[t.agency] || 0) + toNum(t.value); });
    const topAgencies = Object.entries(agencyTotals).sort((a, b) => b[1] - a[1]).slice(0, 5)
      .map(([name, val]) => ({ name, valueFmt: fmtRM(val) }));
    const maxAgencyVal = Math.max(1, ...topAgencies.map(a => agencyTotals[a.name]));
    topAgencies.forEach((a, i) => { a.barPct = (agencyTotals[a.name] / maxAgencyVal * 100) + '%'; a.barColor = WORKLOAD_PASTELS[(i + 3) % WORKLOAD_PASTELS.length]; });

    const parseDeadline = (str) => { const d = new Date(str); return isNaN(d) ? null : d; };
    const upcomingDeadlines = picScope
      .filter(t => t.status === 'In Progress')
      .map(t => ({ ...t, _d: parseDeadline(t.deadline) }))
      .filter(t => t._d)
      .sort((a, b) => a._d - b._d)
      .slice(0, 8)
      .map(t => ({
        id: t.id, name: t.name.length > 55 ? t.name.slice(0, 55) + '…' : t.name,
        agency: t.agency, deadline: t.deadline, staff: t.staff, avatarBg: t.avatarBg,
        initials: t.staff.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase(),
        onClick: () => {
          const totalSell = toNum(t.value);
          const built = this.costingFor(t, totalSell);
          const uploadedCount = Math.round((t.doc / 100) * DOC_TEMPLATE.length);
          const docs = DOC_TEMPLATE.map((d, i) => ({ ...d, id: i, uploaded: i < uploadedCount, uploadedDate: t.created }));
          this.setState({ view: 'detail', prevView: 'dashboard', selectedId: t.id, detailTab: 'overview', costingLines: built.lines, costingSeq: built.seq, costingMeta: { sellFactor: built.sellFactor }, costingSaved: JSON.stringify(built.lines), costingSavedAt: null, ...this.docsFor(t, docs.map(d => ({ ...d, assignee: t.staff }))) });
        },
      }));

    const statusStyle = {
      'In Progress': { bg: 'var(--info-bg)', color: 'var(--info-ink)' },
      'Awarded': { bg: 'var(--good-bg)', color: 'var(--good-ink)' },
      'Done': { bg: 'var(--border-soft)', color: 'var(--muted)' },
      'Lost': { bg: 'var(--bad-bg)', color: 'var(--bad-ink)' },
    };

    const tenders = tendersData.map(t => {
      const s = statusStyle[t.status];
      const uploadedCount = Math.round((t.doc / 100) * DOC_TEMPLATE.length);
      const documents = DOC_TEMPLATE.map((d, i) => {
        const uploaded = i < uploadedCount;
        const badge = FILE_BADGE[d.fileType];
        return {
          ...d, id: i,
          icon: uploaded ? h(ICONS.checkSm) : h(ICONS.clockSm),
          iconBg: uploaded ? 'var(--good-bg)' : 'var(--border-soft)',
          iconColor: uploaded ? 'var(--good-ink)' : 'var(--muted-2)',
          statusLabel: uploaded ? 'Uploaded' : 'Pending',
          statusColor: uploaded ? 'var(--good-ink)' : 'var(--warn-ink)',
          uploaded,
          badgeBg: badge.bg,
          badgeColor: badge.color,
          uploadedDate: t.created,
        };
      });
      const uploadedDocuments = documents.filter(d => d.uploaded);
      const pendingCount = documents.length - uploadedDocuments.length;
      const MON = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      const fmtDate = (v) => {
        if (!v) return '';
        const str = String(v).trim();
        if (/^\d{1,2}\s+[A-Za-z]{3,}\s+\d{4}$/.test(str)) {
          const p = str.split(/\s+/);
          return String(p[0]).padStart(2, '0') + ' ' + p[1].slice(0, 3) + ' ' + p[2];
        }
        const iso = str.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
        if (iso) {
          let y = +iso[1], a = +iso[2], b = +iso[3];
          // canonical YYYY-MM-DD when the 2nd part is a valid month and 3rd isn't
          let d = a, m = b;
          if (a <= 12 && b > 12) { m = a; d = b; }
          if (m >= 1 && m <= 12 && d >= 1 && d <= 31) return String(d).padStart(2, '0') + ' ' + MON[m - 1] + ' ' + y;
          return '';
        }
        const parsed = new Date(str);
        if (!isNaN(parsed)) return String(parsed.getDate()).padStart(2, '0') + ' ' + MON[parsed.getMonth()] + ' ' + parsed.getFullYear();
        return '';
      };
      return {
        ...t,
        woDate: fmtDate(t.woDate),
        deadline: fmtDate(t.deadline) || t.deadline,
        initials: t.staff.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase(),
        deadlineColor: t.urgent ? 'var(--bad-ink)' : 'var(--ink-2)',
        deadlineWeight: t.urgent ? 700 : 400,
        docPct: t.doc + '%',
        docBarColor: t.doc === 100 ? 'var(--good-ink)' : t.doc >= 50 ? 'var(--info-ink)' : 'var(--warn-ink)',
        docLabel: t.doc + '%',
        briefingLabel: t.hasBriefing === 'Yes' ? 'Yes' : 'No',
        briefingBg: t.hasBriefing === 'Yes' ? 'var(--good-bg)' : 'var(--bad-bg)',
        briefingColor: t.hasBriefing === 'Yes' ? 'var(--good-ink)' : 'var(--bad-ink)',
        statusBg: s.bg,
        statusColor: s.color,
        documents,
        uploadedDocuments,
        pendingDocuments: pendingCount > 0,
        pendingLabel: pendingCount + ' document(s) still pending upload.',
        ooInitials: t.oo.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase(),
        submitPrice: t.submitPrice || t.value,
        companyVariant: (() => {
          const ind = Number(t.value.replace(/[^0-9.]/g, ''));
          const sub = Number((t.submitPrice || t.value).replace(/[^0-9.]/g, ''));
          return ind ? ((sub / ind) * 100).toFixed(1) + '%' : '-';
        })(),
        gross: (() => {
          const sub = Number((t.submitPrice || t.value).replace(/[^0-9.]/g, ''));
          const cost = Number((t.submittedCost || t.value).replace(/[^0-9.]/g, '')) * 0.82;
          return sub ? (((sub - cost) / sub) * 100).toFixed(1) + '%' : '-';
        })(),
        winPrice: t.winPrice || t.value,
        winVariant: (() => {
          const ind = Number(t.value.replace(/[^0-9.]/g, ''));
          const win = Number((t.winPrice || t.value).replace(/[^0-9.]/g, ''));
          return ind ? ((win / ind) * 100).toFixed(1) + '%' : '-';
        })(),
        woNumber: t.woNumber || '-', woDate: fmtDate(t.woDate) || fmtDate(t.created) || '',
        mode: t.mode || 'EP', type: t.type || 'Tender',
        publishDate: t.publishDate || '-',
        hasBriefing: t.hasBriefing || 'No', briefingDate: t.briefingDate || '-',
        onClick: () => {
          const totalSell = Number(t.value.replace(/[^0-9.]/g, ''));
          const built = this.costingFor(t, totalSell);
          this.setState({ view: 'detail', prevView: this.state.view, selectedId: t.id, detailTab: 'overview', costingLines: built.lines, costingSeq: built.seq, costingMeta: { sellFactor: built.sellFactor }, costingSaved: JSON.stringify(built.lines), costingSavedAt: null, ...this.docsFor(t, documents.map(d => ({ ...d, assignee: t.staff }))) });
        },
      };
    });

    const isDashboard = this.state.view === 'dashboard';
    const isDetail = this.state.view === 'detail';
    const targetStatus = STATUS_MAP[this.state.view];
    const isListView = !!targetStatus;
    const isDoneView = this.state.view === 'done';
    const isLostView = this.state.view === 'lost';
    const isPerformanceView = this.state.view === 'performance';
    const isQuotationView = this.state.view === 'quotation';
    const selectedTender = isDetail ? tenders.find(t => t.id === this.state.selectedId) : null;

    const isLocked = !!(selectedTender && (selectedTender.status === 'Done' || selectedTender.status === 'Awarded'));
    let costingTotals = null;
    let costing = null, activity = [], tabs = [], taskDocs = [], taskDone = 0;
    if (selectedTender && this.state.taskDocs) {
      taskDocs = this.state.taskDocs.map(d => ({
        ...d,
        onToggle: isLocked ? (() => {}) : () => this.toggleTaskDoc(d.id),
        cursor: isLocked ? 'default' : 'pointer',
        boxBg: d.uploaded ? 'var(--accent)' : 'var(--surface)',
        boxBorder: d.uploaded ? 'var(--accent)' : 'var(--border-2)',
        tickDisplay: d.uploaded ? 'block' : 'none',
        nameColor: d.uploaded ? 'var(--ink)' : 'var(--ink-2)',
        subline: d.uploaded ? 'Marked done' + (d.doneBy ? ' by ' + d.doneBy : '') + (d.uploadedDate ? ' · ' + d.uploadedDate : '') : (d.meta || 'Required').split('·')[0].trim() + ' · tick when prepared',
        onView: () => this.openDocPreview(d),
        onFileSelected: isLocked ? (() => {}) : (e) => this.handleFileUpload(d.id, e.target.files[0]),
        uploadedInverse: !d.uploaded && !isLocked,
        iconBg: d.uploaded ? 'var(--good-bg)' : 'var(--border-soft)',
        iconColor: d.uploaded ? 'var(--good-ink)' : 'var(--muted-2)',
        statusLabel: d.uploaded ? 'Done' : 'Pending',
        statusColor: d.uploaded ? 'var(--good-ink)' : 'var(--warn-ink)',
      }));
      taskDone = taskDocs.filter(d => d.uploaded).length;
    }
    if (selectedTender && this.state.costingLines) {
      const round2 = (n) => Math.round((n + Number.EPSILON) * 100) / 100;
      const fmt = (n) => 'RM ' + round2(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      const fmtNum = (n) => round2(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      const fmtPlain = fmtNum;
      const rawLines = this.state.costingLines;
      const COST_VENDORS = ['Dell Technologies (M) Sdn Bhd', 'HP PPS Malaysia Sdn Bhd', 'Lenovo Malaysia Sdn Bhd', 'ECS Astar Sdn Bhd', 'VSTECS Berhad', 'Ingram Micro Malaysia Sdn Bhd', 'Cisco Systems (M) Sdn Bhd', 'Microsoft Malaysia Sdn Bhd'];
      const freqMult = (l) => l.months || 1;
      const lineCost = (l) => (l.subItems.length ? l.qty * l.subItems.reduce((s, si) => s + si.unitCostRaw, 0) : l.qty * l.unitCostRaw) * freqMult(l);
      const unitPrice = (l) => Math.ceil(((l.subItems.length ? l.subItems.reduce((s, si) => s + si.unitCostRaw, 0) : l.unitCostRaw) / (l.markupRaw || 1)) - 1e-9);
      const lineSell = (l) => unitPrice(l) * l.qty * freqMult(l);
      const totalCost = rawLines.reduce((sum, l) => sum + lineCost(l), 0);
      const totalSell = rawLines.reduce((sum, l) => sum + lineSell(l), 0);
      const margin = totalSell - totalCost;
      costingTotals = { totalCost, totalSell };
      const marginPct = totalSell ? (margin / totalSell * 100).toFixed(1) : '0.0';
      const costPct = totalSell ? (totalCost / totalSell * 100).toFixed(1) : '0.0';
      const lines = rawLines.map(l => {
        const hasSub = l.subItems.length > 0;
        const total = lineCost(l);
        const sell = lineSell(l);
        const noop = (e) => {};
        return {
          id: l.id, item: l.item,
          unit: l.unit,
          qtyRaw: l.qty, unitCostRaw: l.unitCostRaw, markupRaw: l.markupRaw, markupInput: l.markupRaw.toFixed(2),
          unitCostInput: (hasSub ? l.subItems.reduce((s, si) => s + si.unitCostRaw, 0) : l.unitCostRaw).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
          priceUnit: fmtPlain(unitPrice(l)),
          total: fmtPlain(total), sell: fmtPlain(sell),
          hasSub, locked: isLocked,
          costDisabled: hasSub || isLocked,
          addBreakdownDisplay: isLocked ? 'none' : 'block',
          removeDisplay: isLocked ? 'none' : 'block',
          months: l.months,
          onMonthsChange: isLocked ? noop : (e) => this.updateLineField(l.id, 'months', e.target.value),
          onMarkupChange: isLocked ? noop : (e) => this.updateLineField(l.id, 'markupRaw', e.target.value),
          subItems: l.subItems.map(si => ({
            id: si.id, item: si.item, qty: si.qty, unit: si.unit,
            unitCost: fmtNum(si.unitCostRaw),
            qtyRaw: si.qty, unitCostRawVal: si.unitCostRaw, unitCostInput: si.unitCostRaw.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
            total: fmtPlain(si.qty * si.unitCostRaw),
            locked: isLocked,
            onRemove: isLocked ? noop : () => this.removeSubItem(l.id, si.id),
            onQtyChange: isLocked ? noop : (e) => this.updateSubField(l.id, si.id, 'qty', e.target.value),
            onCostChange: isLocked ? noop : (e) => this.updateSubField(l.id, si.id, 'unitCostRaw', e.target.value),
            onNameChange: isLocked ? noop : (e) => this.updateSubField(l.id, si.id, 'item', e.target.value),
            onUnitChange: isLocked ? noop : (e) => this.updateSubField(l.id, si.id, 'unit', e.target.value),
            vendor: si.vendor || '', quoteUrl: si.quoteUrl || '',
            quoteHref: /^https?:\/\//i.test(si.quoteUrl || '') ? si.quoteUrl : 'https://' + (si.quoteUrl || ''),
            linkEditing: !isLocked && this.state.costLinkEdit === l.id + ':' + si.id,
            hasLink: !!si.quoteUrl && this.state.costLinkEdit !== l.id + ':' + si.id,
            showAddLink: !isLocked && !si.quoteUrl && this.state.costLinkEdit !== l.id + ':' + si.id,
            editDisplay: isLocked ? 'none' : 'inline',
            vendorInput: this.state.vendorMenu && this.state.vendorMenu.key === l.id + ':' + si.id ? this.state.vendorMenu.q : (si.vendor || ''),
            vendorBorder: this.state.vendorMenu && this.state.vendorMenu.key === l.id + ':' + si.id ? 'var(--accent-ink-2)' : 'var(--border)',
            chevRot: this.state.vendorMenu && this.state.vendorMenu.key === l.id + ':' + si.id ? '180deg' : '0deg',
            onVendorFocus: isLocked ? noop : (e) => { const r = e.target.getBoundingClientRect(); const below = window.innerHeight - r.bottom > 260; this.setState({ vendorMenu: { key: l.id + ':' + si.id, q: si.vendor || '', all: true, hi: -1, top: below ? r.bottom + 4 : Math.max(8, r.top - 248), left: r.left, width: Math.max(r.width, 220), set: (v) => this.updateSubField(l.id, si.id, 'vendor', v) } }); e.target.select(); },
            onVendorType: isLocked ? noop : (e) => { const q = e.target.value; this.setState(st => ({ vendorMenu: st.vendorMenu ? { ...st.vendorMenu, q, all: false, hi: q ? 0 : -1 } : st.vendorMenu })); },
            onVendorBlur: () => { if (this._vmKeep) { this._vmKeep = false; return; } this.setState({ vendorMenu: null }); },
            onVendorKey: (e) => this._vendorKey(e),
            onQuoteUrlChange: isLocked ? noop : (e) => this.updateSubField(l.id, si.id, 'quoteUrl', e.target.value.trim()),
            onLinkEdit: isLocked ? noop : () => this.setState({ costLinkEdit: l.id + ':' + si.id }),
            onLinkDone: () => this.setState({ costLinkEdit: null }),
          })),
          onAddBreakdown: isLocked ? noop : () => this.addBreakdown(l.id),
          onRemove: isLocked ? noop : () => this.removeLineItem(l.id),
          onQtyChange: isLocked ? noop : (e) => this.updateLineField(l.id, 'qty', e.target.value),
          onCostChange: isLocked ? noop : (e) => this.updateLineField(l.id, 'unitCostRaw', e.target.value),
          onNameChange: isLocked ? noop : (e) => this.updateLineField(l.id, 'item', e.target.value),
          onUnitChange: isLocked ? noop : (e) => this.updateLineField(l.id, 'unit', e.target.value),
            vendor: l.vendor || '', quoteUrl: l.quoteUrl || '',
            quoteHref: /^https?:\/\//i.test(l.quoteUrl || '') ? l.quoteUrl : 'https://' + (l.quoteUrl || ''),
            linkEditing: !isLocked && this.state.costLinkEdit === String(l.id),
            hasLink: !!l.quoteUrl && this.state.costLinkEdit !== String(l.id),
            showAddLink: !isLocked && !l.quoteUrl && this.state.costLinkEdit !== String(l.id),
            editDisplay: isLocked ? 'none' : 'inline',
            vendorInput: this.state.vendorMenu && this.state.vendorMenu.key === String(l.id) ? this.state.vendorMenu.q : (l.vendor || ''),
            vendorBorder: this.state.vendorMenu && this.state.vendorMenu.key === String(l.id) ? 'var(--accent-ink-2)' : 'var(--border)',
            chevRot: this.state.vendorMenu && this.state.vendorMenu.key === String(l.id) ? '180deg' : '0deg',
            onVendorFocus: isLocked ? noop : (e) => { const r = e.target.getBoundingClientRect(); const below = window.innerHeight - r.bottom > 260; this.setState({ vendorMenu: { key: String(l.id), q: l.vendor || '', all: true, hi: -1, top: below ? r.bottom + 4 : Math.max(8, r.top - 248), left: r.left, width: Math.max(r.width, 220), set: (v) => this.updateLineField(l.id, 'vendor', v) } }); e.target.select(); },
            onVendorType: isLocked ? noop : (e) => { const q = e.target.value; this.setState(st => ({ vendorMenu: st.vendorMenu ? { ...st.vendorMenu, q, all: false, hi: q ? 0 : -1 } : st.vendorMenu })); },
            onVendorBlur: () => { if (this._vmKeep) { this._vmKeep = false; return; } this.setState({ vendorMenu: null }); },
            onVendorKey: (e) => this._vendorKey(e),
            onQuoteUrlChange: isLocked ? noop : (e) => this.updateLineField(l.id, 'quoteUrl', e.target.value.trim()),
            onLinkEdit: isLocked ? noop : () => this.setState({ costLinkEdit: String(l.id) }),
            onLinkDone: () => this.setState({ costLinkEdit: null }),
        };
      });
      const vendorOptions = [...new Set([...COST_VENDORS, ...rawLines.flatMap(l => [l.vendor, ...l.subItems.map(si => si.vendor)]).filter(Boolean)])].sort((a, b) => a.localeCompare(b));
      costing = {
        totalCost: fmt(totalCost), totalSell: fmt(totalSell),
        margin: fmt(margin), marginPct: marginPct.replace('.', ',') + '%',
        costPct: costPct + '%', marginPctPlain: marginPct + '%', lines, vendorOptions,
        ...this._vendorMenuVals(vendorOptions),
        locked: isLocked,
        addLineDisplay: isLocked ? 'none' : 'inline-block',
        onAddLineItem: isLocked ? (() => {}) : () => this.addLineItem(),
        onOpenBulkImport: isLocked ? (() => {}) : () => this.openBulkImport(),
      };
      activity = ACTIVITY_LOG.map(a => ({ ...a }));
      const tabConfig = ['overview', 'costing', 'pd', 'documents', 'activity'];
      const tabLabels = { overview: 'Overview', costing: 'Costing', pd: 'PD', documents: 'Documents', activity: 'Activity' };
      tabs = tabConfig.map(key => ({
        key,
        label: tabLabels[key],
        active: this.state.detailTab === key,
        color: this.state.detailTab === key ? 'var(--accent-ink-2)' : 'var(--muted)',
        weight: this.state.detailTab === key ? 700 : 500,
        tabBg: this.state.detailTab === key ? 'var(--accent-tint)' : 'transparent',
        borderColor: 'transparent',
        onClick: () => this.setState({ detailTab: key }),
      }));
    }
    const detailTab = this.state.detailTab;

    const staffOptions = ['All', ...Array.from(new Set(tendersData.map(t => t.staff)))];
    const picOptions = Object.keys(STAFF_COLORS);
    const ministryOptions = MINISTRY_OPTIONS;
    const ministrySearch = this.state.ministrySearch;
    const filteredMinistries = (ministrySearch
      ? MINISTRY_OPTIONS.filter(m => m.toLowerCase().includes(ministrySearch.toLowerCase()))
      : MINISTRY_OPTIONS
    ).slice(0, 30).map(m => ({ label: m, onClick: () => this.selectMinistry(m) }));

    let filteredTenders = [];
    if (isListView) {
      const q = this.state.search.trim().toLowerCase();
      filteredTenders = tenders.filter(t =>
        t.status === targetStatus &&
        (this.state.staffFilter === 'All' || t.staff === this.state.staffFilter) &&
        (!this.state.agencyFilter || this.state.agencyFilter === 'All' || t.agency === this.state.agencyFilter) &&
        (!this.state.modeFilter || this.state.modeFilter === 'All' || t.mode === this.state.modeFilter) &&
        (!this.state.dateFrom || (new Date(t.deadline) >= new Date(this.state.dateFrom))) &&
        (!this.state.dateTo || (new Date(t.deadline) <= new Date(this.state.dateTo))) &&
        (!this.state.mineOnly || t.staff === 'Siti Aisyah') &&
        (q === '' || t.name.toLowerCase().includes(q) || t.agency.toLowerCase().includes(q) || String(t.code || '').toLowerCase().includes(q))
      );
      const dir = this.state.deadlineSortDir === 'desc' ? -1 : 1;
      filteredTenders = filteredTenders.slice().sort((a, b) => {
        const da = new Date(a.deadline), db = new Date(b.deadline);
        const va = isNaN(da) ? 0 : da.getTime();
        const vb = isNaN(db) ? 0 : db.getTime();
        return (va - vb) * dir;
      });
    }
    const totalPages = Math.max(1, Math.ceil(filteredTenders.length / this.PAGE_SIZE));
    const page = Math.min(this.state.page, totalPages);
    const pageStart = (page - 1) * this.PAGE_SIZE;
    const pagedTenders = filteredTenders.slice(pageStart, pageStart + this.PAGE_SIZE);
    const pageNumbers = Array.from({ length: totalPages }, (_, i) => ({
      n: i + 1, active: i + 1 === page,
      bg: i + 1 === page ? 'var(--accent)' : 'var(--surface)', color: i + 1 === page ? '#fff' : 'var(--muted)',
      onClick: () => this.setState({ page: i + 1 }),
    }));

    const titles = {
      dashboard: ['Tender Dashboard', 'Government bidding tender overview'],
      inprogress: ['In Progress Tenders', 'Tenders currently being worked on'],
      awarded: ['Awarded Tenders', 'Tenders won and confirmed'],
      done: ['Done Tenders', 'Completed, awarded-closed tenders'],
      lost: ['Lost Tenders', 'Tenders lost or with no award news'],
      performance: ['Status', 'Per-PIC tender performance overview'],
      quotation: ['Quotation', 'Quick quotations outside the formal tender process'],
      settings: ['Settings', 'Account and workspace settings'],
    };
    const [viewTitle, viewSubtitle] = isDetail && selectedTender
      ? [selectedTender.name, selectedTender.agency]
      : (titles[this.state.view] || titles.dashboard);

    return {
      tableMinWidth: isDoneView ? '1400px' : isLostView ? '1630px' : this.state.view === 'inprogress' ? '1320px' : '1230px',
      winRate: winRate + '%', totalValueFmt: fmtRM(totalValue),
      performanceRows: Object.keys(STAFF_COLORS).map(name => {
        const owned = tendersData.filter(t => t.staff === name);
        const won = owned.filter(t => t.status === 'Awarded' || t.status === 'Done');
        const lost = owned.filter(t => t.status === 'Lost');
        const inProgress = owned.filter(t => t.status === 'In Progress');
        const totalAmount = owned.reduce((s, t) => s + toNum(t.value), 0);
        const winRatePic = owned.length ? Math.round((won.length / owned.length) * 100) : 0;
        return {
          name, initials: name.split(' ').map(x => x[0]).join('').slice(0, 2).toUpperCase(), avatarBg: STAFF_COLORS[name],
          total: owned.length, won: won.length, lost: lost.length, inProgress: inProgress.length,
          totalAmount: fmtRM(totalAmount), winRatePic: winRatePic + '%',
        };
      }),
      donutGradient, donutLegend, staffWorkload, topAgencies, upcomingDeadlines,
      picSumMonth, picSumMonths, picSumRows, picSumTotalCount, picSumTotal, epStat, nonEpStat, epBarPct,
      epModes: [{ ...epStat, dot: 'var(--accent)' }, { ...nonEpStat, dot: 'var(--muted-2)' }],
      picSumMonthLabel: (picSumMonths.find(m => m.value === picSumMonth) || { label: 'All' }).label.replace(/[()]/g, ''),
      dashCols: (window.innerWidth || 1400) < 1180 ? 'minmax(0,1fr)' : 'minmax(300px,1fr) minmax(0,2fr)',
      dashTotal: picScope.length, noUpcoming: upcomingDeadlines.length === 0,
      picBarDisplay: (window.innerWidth || 1400) < 640 ? 'none' : 'table-cell',
      onPicSumMonth: (e) => this.setState({ picSumMonth: e.target.value }),
      hasUpcoming: upcomingDeadlines.length > 0,
      showDocsColumn: !isDoneView && !isLostView,
      showBriefingColumn: this.state.view === 'inprogress',
      ...this.quoteVals(isMobile, vw),
      navGroups, kpis, tenders, isDashboard, isListView, isDetail, isDoneView, isLostView, isPerformanceView, selectedTender,
      showRegisterButton: this.state.view === 'inprogress' && !isDetail,
      costing, activity, tabs, detailTab,
      ...(() => {
        const cats = [["collection","Collection","Customer payment collection schedule according to contract terms."],["principal","Principal","Costs paid to principals."],["distributor","Distributor","Costs paid to distributors."],["partner","Partner","Costs paid to partners."],["finance","Finance Cost","Financing and bank charges."],["sst","Tax (SST)","Sales & service tax payable."],["misc","Misc, Training & Travel","Miscellaneous, training and travel expenses."],["manpower","Internal Resources (Manpower)","Internal manpower costs."]];
        const cur = this.state.pdCat || 'collection';
        const c = cats.find(x => x[0] === cur) || cats[0];
        return {
          pdCatTabs: cats.map(([k, label]) => ({ label, onClick: () => this.setState({ pdCat: k }), weight: k === cur ? 700 : 500, bg: k === cur ? 'var(--accent)' : 'var(--surface)', color: k === cur ? '#fff' : 'var(--ink-2)', border: k === cur ? 'var(--accent)' : 'var(--border-2)' })),
          pdCatDesc: c[2], pdCatLabel: c[1], pdIsCollection: cur === 'collection', pdIsOtherCat: cur !== 'collection',
          ...(() => {
            const tenderStore = (this.state.pdStore || {})[this._pdKey()] || {};
            const rows = tenderStore[cur] || [];
            const n = (x) => Number(String(x || '').replace(/,/g, '')) || 0;
            const f = (x) => 'RM ' + x.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const isProc = ['principal', 'distributor', 'partner'].includes(cur);
            const h = (id, field) => (e) => this.updatePdLine(id, field, e.target.value);
            const tone = { good: ['var(--good-bg)', 'var(--good-ink)'], bad: ['var(--bad-bg)', 'var(--bad-ink)'], info: ['var(--info-bg)', 'var(--info-ink)'], warn: ['var(--warn-bg)', 'var(--warn-ink)'], none: ['var(--subtle-2)', 'var(--muted)'] };
            const t = { budget: 0, pr: 0, po: 0, inv: 0, paid: 0, ap: 0, variance: 0, actual: 0 };
            const pdRows = rows.map(r => {
              const common = { ...r, onName: h(r.id, 'name'), onRef: h(r.id, 'ref'), onBudget: h(r.id, 'budget'), onRemove: () => this.removePdLine(r.id) };
              const b = n(r.budget); t.budget += b;
              if (isProc) {
                const pr = n(r.pr), po = n(r.po), inv = n(r.inv), paid = n(r.paid);
                const ap = Math.max(0, inv - paid), spend = Math.max(po, inv), variance = b - spend;
                t.pr += pr; t.po += po; t.inv += inv; t.paid += paid; t.ap += ap; t.variance += variance;
                const st = b && spend > b ? ['Over Budget', 'bad'] : inv && paid >= inv ? ['Paid', 'good'] : inv ? ['Invoiced', 'warn'] : po ? ['PO Issued', 'info'] : pr ? ['PR Raised', 'info'] : ['Pending', 'none'];
                return { ...common, onPr: h(r.id, 'pr'), onPo: h(r.id, 'po'), onInv: h(r.id, 'inv'), onPaid: h(r.id, 'paid'),
                  ap: f(ap), apColor: ap > 0 ? 'var(--warn-ink)' : 'var(--ink)',
                  variance: f(variance), varColor: variance < 0 ? 'var(--bad-ink)' : 'var(--good-ink)',
                  status: st[0], statusBg: tone[st[1]][0], statusColor: tone[st[1]][1] };
              }
              const a = n(r.actual); t.actual += a;
              const st = b && a > b ? ['Over Budget', 'bad'] : a ? ['Within Budget', 'good'] : ['Pending', 'none'];
              return { ...common, onActual: h(r.id, 'actual'), status: st[0], statusBg: tone[st[1]][0], statusColor: tone[st[1]][1] };
            });
            const collExtra = (tenderStore.collection || []).map(r => ({ ...r, onName: h(r.id, 'name'), onNote: h(r.id, 'note'), onDate: h(r.id, 'date'), onBudget: h(r.id, 'budget'), onRemove: () => this.removePdLine(r.id) }));
            const collTotal = 1250000 + (tenderStore.collection || []).reduce((sum, r) => sum + n(r.budget), 0);
            return {
              pdIsProc: isProc, pdIsSimple: cur !== 'collection' && !isProc,
              pdRows: cur === 'collection' ? [] : pdRows, pdRowsEmpty: cur !== 'collection' && pdRows.length === 0, pdRowsAny: cur !== 'collection' && pdRows.length > 0,
              pdTotals: Object.fromEntries(Object.entries(t).map(([k, v]) => [k, f(v)])),
              pdCollExtra: collExtra, pdCollTotal: f(collTotal),
              onAddPdLine: () => this.addPdLine(),
            };
          })(),
        };
      })(),
      ...(() => {
        const raw = selectedTender && (this.state.tendersData || []).find(x => x.id === this.state.selectedId);
        let rows = [
          ['Revenue', '', 'RM 1,250,000.00', '', 'RM 375,000.00', '', 'main'],
          ['Less: Cost of Sales (COS)', '', 'RM 651,000.00', '', 'RM 1,065,000.00', '', 'sub'],
          ['Less: Internal Resources, Tax', '', 'RM 132,000.00', '', 'RM 62,000.00', '', 'sub'],
          ['Less: Project Charges', '(9% of budgeted revenue)', 'RM 112,500.00', '', 'RM 112,500.00', '', 'sub'],
          ['Gross Profit (GP)', '', 'RM 599,000.00', '(47.9%)', 'RM -690,000.00', '(-184.0%)', 'total'],
          ['Commission', '((GP − approved margin) × 50%)', 'RM 205,750.00', '', 'RM 0.00', '', 'sub'],
          ['Net Profit', '', 'RM 354,500.00', '(28.4%)', 'RM -864,500.00', '(-230.5%)', 'net'],
        ];
        let pdApproved = 'RM 187,500.00', pdApprovedNote = '15% · Managed Services';
        if (raw && raw.costingPreset === 'skimRondaan' && costingTotals) {
          const f = (n) => 'RM ' + (Math.round(n * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
          const pct = (n, base) => '(' + (base ? (n / base * 100).toFixed(1) : '0.0') + '%)';
          const R = costingTotals.totalSell, C = costingTotals.totalCost;
          const charges = R * 0.09, internal = 0;
          const gp = R - C - internal - charges;
          const approved = R * 0.15;
          const comm = Math.max(0, (gp - approved) * 0.5);
          const net = gp - comm;
          rows = [
            ['Revenue', '', f(R), '', f(R), '', 'main'],
            ['Less: Cost of Sales (COS)', '', f(C), '', f(C), '', 'sub'],
            ['Less: Internal Resources, Tax', '', f(internal), '', f(internal), '', 'sub'],
            ['Less: Project Charges', '(9% of budgeted revenue)', f(charges), '', f(charges), '', 'sub'],
            ['Gross Profit (GP)', '', f(gp), pct(gp, R), f(gp), pct(gp, R), 'total'],
            ['Commission', '((GP − approved margin) × 50%)', f(comm), '', f(comm), '', 'sub'],
            ['Net Profit', '', f(net), pct(net, R), f(net), pct(net, R), 'net'],
          ];
          pdApproved = f(approved); pdApprovedNote = '15% of revenue · from Costing';
        }
        return {
          pdApproved, pdApprovedNote,
          pdPnl: rows.map(([label, hint, budget, budgetPct, actual, actualPct, kind]) => ({ label, hint, budget, budgetPct, actual, actualPct,
            weight: kind === 'total' || kind === 'net' ? 700 : kind === 'main' ? 600 : 500,
            labelColor: kind === 'sub' ? 'var(--muted)' : 'var(--ink)',
            budgetColor: kind === 'sub' ? 'var(--muted)' : (kind === 'net' && budget.includes('-')) ? 'var(--bad-ink)' : 'var(--ink)',
            actualColor: kind === 'sub' ? 'var(--muted)' : (kind === 'net' && actual.includes('-')) ? 'var(--bad-ink)' : 'var(--ink)' })),
        };
      })(),
      pdCollections: [
        { name: 'Payment 1 (Down Payment)', note: '30% initial collection', date: '20 Jan 2026', scheduled: 'RM 375,000.00', invoiced: 'RM 375,000.00', invoicedNote: '1 invoice', collected: 'RM 375,000.00', collectedNote: '1 receipt', ar: 'RM 0.00', status: 'Paid / Completed', st: 'paid', invLabel: 'INV1', rcvLabel: 'RCV1', inv: true, rcv: true },
        { name: 'Payment 2 (Progress)', note: '30% after installation', date: '15 Apr 2026', scheduled: 'RM 375,000.00', invoiced: 'RM 375,000.00', invoicedNote: '1 invoice', collected: '—', collectedNote: '', ar: 'RM 375,000.00', status: 'Submitted', st: 'sub', invLabel: 'INV1', rcvLabel: 'RCV', inv: true, rcv: false },
        { name: 'Payment 3 (Final)', note: '40% balance after handover', date: '15 Jul 2026', scheduled: 'RM 500,000.00', invoiced: '—', invoicedNote: '', collected: '—', collectedNote: '', ar: '—', status: 'Pending', st: 'pend', invLabel: 'INV', rcvLabel: 'RCV', inv: false, rcv: false },
      ].map(r => ({ ...r,
        statusBg: r.st === 'paid' ? 'var(--good-bg)' : r.st === 'sub' ? 'var(--info-bg)' : 'var(--subtle-2)',
        statusColor: r.st === 'paid' ? 'var(--good-ink)' : r.st === 'sub' ? 'var(--info-ink)' : 'var(--muted)',
        arColor: r.st === 'sub' ? 'var(--warn-ink)' : 'var(--ink)',
        invColor: r.inv ? '#2F7D32' : 'var(--muted)', rcvColor: r.rcv ? '#2F7D32' : 'var(--muted)' })),
      pdCashFlow: [
        ['Jan 2026', 'RM 375,000.00', 'RM 112,500.00', 'RM 262,500.00'],
        ['Feb 2026', '', 'RM 263,000.00', 'RM -500.00'],
        ['Mar 2026', '', 'RM 64,000.00', 'RM -64,500.00'],
        ['Apr 2026', '', '', 'RM -64,500.00'],
        ['May 2026', '', '', 'RM -64,500.00'],
        ['Jun 2026', '', '', 'RM -64,500.00'],
        ['Jul 2026', '', '', 'RM -64,500.00'],
        ['Aug 2026', '', '', 'RM -64,500.00'],
        ['Sep 2026', '', 'RM 800,000.00', 'RM -864,500.00'],
      ].map(([month, i, o, b]) => ({ month, inflow: i || '—', outflow: o || '—',
        inColor: i ? 'var(--good-ink)' : 'var(--muted-3)', outColor: o ? 'var(--bad-ink)' : 'var(--muted-3)',
        balance: b, balColor: b.includes('-') ? 'var(--bad-ink)' : 'var(--ink)' })),
      pdLowMargin: PD_PROJECTS.filter(p => p.low).map(p => ({ ...p, ...p.low, stageBg: p.won ? 'var(--good-bg)' : 'var(--subtle-2)', stageColor: p.won ? 'var(--good-ink)' : 'var(--muted)',
        actualColor: p.low.actualNeg ? 'var(--bad-ink)' : 'var(--warn-ink)' })),
      docsSavedMsg: this.state.docsSavedAt ? 'Saved ' + this.state.docsSavedAt : '',
      costingDirty: !!(this.state.costingLines && this.state.costingSaved != null && JSON.stringify(this.state.costingLines) !== this.state.costingSaved),
      ...(() => { const d = !!(this.state.costingLines && this.state.costingSaved != null && JSON.stringify(this.state.costingLines) !== this.state.costingSaved); return { costingSaveDisabled: !d, costingSaveBg: d ? 'var(--accent)' : 'var(--border)', costingSaveColor: d ? '#fff' : 'var(--muted-3)', costingSaveCursor: d ? 'pointer' : 'not-allowed' }; })(),
      costingSavedMsg: this.state.costingSavedAt && this.state.costingLines && JSON.stringify(this.state.costingLines) === this.state.costingSaved ? 'Costing saved at ' + this.state.costingSavedAt : '',
      onSaveCosting: () => this.saveCosting(),
      onDiscardCosting: () => this.setState(s => ({ costingLines: JSON.parse(s.costingSaved) })),
      showOverview: detailTab === 'overview', showCosting: detailTab === 'costing',
      showPd: detailTab === 'pd', showDocuments: detailTab === 'documents', showActivity: detailTab === 'activity',
      filteredTenders: pagedTenders, filteredCount: filteredTenders.length,
      page, totalPages, pageNumbers,
      onPrevPage: () => this.setState(s => ({ page: Math.max(1, s.page - 1) })),
      onNextPage: () => this.setState(s => ({ page: Math.min(totalPages, s.page + 1) })),
      showingStart: filteredTenders.length ? pageStart + 1 : 0,
      showingEnd: Math.min(pageStart + this.PAGE_SIZE, filteredTenders.length),
      noResults: isListView && filteredTenders.length === 0,
      search: this.state.search,
      staffFilter: this.state.staffFilter,
      staffOptions,
      deadlineSortDir: this.state.deadlineSortDir,
      deadlineSortArrow: this.state.deadlineSortDir === 'desc' ? ' \u2193' : ' \u2191',
      onToggleDeadlineSort: () => this.setState(s => ({ deadlineSortDir: s.deadlineSortDir === 'desc' ? 'asc' : 'desc' })),
      onSearchChange: (e) => this.setState({ search: e.target.value, page: 1 }),
      onStaffChange: (e) => this.setState({ staffFilter: e.target.value, page: 1 }),
      onBack: () => this.setState({ view: this.state.prevView, selectedId: null }),
      viewTitle, viewSubtitle,
      icons: { search: h(ICONS.search), plus: h(ICONS.plus), chevronDown: h(ICONS.chevronDown) },
      sidebarWidth: collapsed ? '76px' : '244px',
      sidebarCollapsed: collapsed,
      isMobile, drawerOpen: isDrawer && this.state.mobileNavOpen,
      onCloseDrawer: () => this.setState({ mobileNavOpen: false }),
      sidebarPos: isDrawer ? 'fixed' : 'sticky',
      sidebarTransform: isDrawer && !this.state.mobileNavOpen ? 'translateX(-105%)' : 'none',
      sidebarShadow: isDrawer && this.state.mobileNavOpen ? '0 20px 60px rgba(28,26,22,0.25)' : 'none',
      hamburgerDisplay: isDrawer ? 'flex' : 'none',
      headerPad: isMobile ? '16px 16px 6px' : '20px 26px 14px',
      contentPad: isMobile ? '14px 14px 24px' : isTablet ? '18px 18px' : '24px 28px',
      contentGap: isMobile ? '14px' : '20px',
      cardPad: isMobile ? '18px 16px' : '24px 26px',
      gridPd: vw < 1280 ? 'minmax(0,1fr)' : 'repeat(2,minmax(0,1fr))',
      grid2: isTablet ? 'minmax(0,1fr)' : 'repeat(2,minmax(0,1fr))',
      grid3: isMobile ? 'minmax(0,1fr)' : 'repeat(3,minmax(0,1fr))',
      grid4: isMobile ? 'repeat(2,minmax(0,1fr))' : isTablet ? 'repeat(2,minmax(0,1fr))' : 'repeat(4,minmax(0,1fr))',
      tableDisplay: isMobile ? 'none' : 'block',
      logoTextDisplay: collapsed ? 'none' : 'block',
      collapseBtnDisplay: collapsed ? 'none' : 'flex',
      collapseTitle: collapsed ? 'Expand sidebar' : 'Collapse sidebar',
      pageHeadingDisplay: isDashboard || isDetail ? 'none' : 'block',
      totalTenders: tendersData.length,
      logoutDisplay: collapsed ? 'none' : 'flex',
      headerDisplay: isDashboard || isDetail ? 'none' : 'flex',
      onBrandEnter: () => this.setState({ brandHover: true }),
      onBrandLeave: () => this.setState({ brandHover: false }),
      brandMarkBg: collapsed && this.state.brandHover ? 'var(--hover)' : 'var(--chip)',
      brandMarkColor: collapsed && this.state.brandHover ? 'var(--ink)' : 'var(--accent)',
      brandLetterDisplay: collapsed && this.state.brandHover ? 'none' : 'block',
      brandExpandDisplay: collapsed && this.state.brandHover ? 'block' : 'none',
      theme: this.state.theme === 'dark' ? 'dark' : 'light',
      themeIcon: this.state.theme === 'dark'
        ? { __html: '<circle cx="12" cy="12" r="4.2"></circle><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M18.4 5.6L17 7M7 17l-1.4 1.4"></path>' }
        : { __html: '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"></path>' },
      onToggleTheme: () => this.setState({ theme: this.state.theme === 'dark' ? 'light' : 'dark' }),
      alertCount: upcomingDeadlines.length,
      mineActive: !!this.state.mineOnly,
      mineBg: this.state.mineOnly ? 'var(--accent)' : 'var(--surface)',
      mineBorder: this.state.mineOnly ? 'var(--accent)' : 'var(--border-2)',
      mineColor: this.state.mineOnly ? '#fff' : '#6B7280',
      mineWeight: this.state.mineOnly ? 700 : 600,
      onToggleMine: () => this.setState({ mineOnly: !this.state.mineOnly, page: 1 }),
      onToggleFilters: () => this.setState({ filtersOpen: !this.state.filtersOpen }),
      filtersOpen: !!this.state.filtersOpen,
      agencyFilter: this.state.agencyFilter || 'All',
      agencyOptions: ['All', ...Array.from(new Set(tendersData.map(t => t.agency))).sort()],
      onAgencyChange: (e) => this.setState({ agencyFilter: e.target.value, page: 1 }),
      modeFilter: this.state.modeFilter || 'All',
      modeOptions: ['All', ...Array.from(new Set(tendersData.map(t => t.mode).filter(Boolean))).sort()],
      onModeChange: (e) => this.setState({ modeFilter: e.target.value, page: 1 }),
      dateFrom: this.state.dateFrom || '',
      dateTo: this.state.dateTo || '',
      onDateFrom: (e) => this.setState({ dateFrom: e.target.value, page: 1 }),
      onDateTo: (e) => this.setState({ dateTo: e.target.value, page: 1 }),
      onClearFilters: () => this.setState({ staffFilter: 'All', agencyFilter: 'All', modeFilter: 'All', dateFrom: '', dateTo: '', mineOnly: false, search: '', page: 1 }),
      filterCount: activeFilterCount,
      filterBadgeDisplay: activeFilterCount ? 'inline-flex' : 'none',
      topSearch: this.state.search || '',
      onTopSearch: (e) => this.setState({ search: e.target.value, page: 1 }),
      logoJustify: collapsed ? 'center' : 'flex-start',
      userInfoDisplay: collapsed ? 'none' : 'block',
      onToggleSidebar: () => this.toggleSidebar(),
      collapseIcon: collapsed ? h(ICONS.chevronRight) : h(ICONS.chevronLeft),
      showRegisterModal: this.state.showRegisterModal,
      form: this.state.form,
      picOptions, ministryOptions,
      ministrySearch, filteredMinistries,
      ministryDropdownOpen: this.state.ministryDropdownOpen,
      ministryDropdownDisplay: this.state.ministryDropdownOpen ? 'block' : 'none',
      onMinistrySearchChange: (e) => { this.setState({ ministrySearch: e.target.value, ministryDropdownOpen: true }); this.updateForm('ministry', e.target.value); },
      onMinistryFocus: () => this.setState({ ministryDropdownOpen: true }),
      ministryRef: (el) => { this._ministryRef = el; },
      modeRef: (el) => { this._refs.mode = el; },
      typeRef: (el) => { this._refs.type = el; },
      picRef: (el) => { this._refs.pic = el; },
      ooRef: (el) => { this._refs.oo = el; },
      onToggleMode: () => this.toggleDropdown('mode'),
      onToggleType: () => this.toggleDropdown('type'),
      onTogglePic: () => this.toggleDropdown('pic'),
      onToggleOo: () => this.toggleDropdown('oo'),
      modeDropdownDisplay: this.state.openDropdown === 'mode' ? 'block' : 'none',
      typeDropdownDisplay: this.state.openDropdown === 'type' ? 'block' : 'none',
      picDropdownDisplay: this.state.openDropdown === 'pic' ? 'block' : 'none',
      ooDropdownDisplay: this.state.openDropdown === 'oo' ? 'block' : 'none',
      modeLabel: this.state.form.mode || 'Select mode',
      typeLabel: this.state.form.type || 'Select type',
      picLabel: this.state.form.pic || 'Select staff',
      ooLabel: this.state.form.oo || 'Select staff',
      modeOptionItems: ['EP', 'Non-EP'].map(v => ({ label: v, onClick: () => { this.updateForm('mode', v); this.setState({ openDropdown: null }); } })),
      typeOptionItems: ['Tender', 'Quotation', 'Direct Nego'].map(v => ({ label: v, onClick: () => { this.updateForm('type', v); this.setState({ openDropdown: null }); } })),
      picOptionItems: picOptions.map(v => ({ label: v, onClick: () => { this.updateForm('pic', v); this.setState({ openDropdown: null }); } })),
      ooOptionItems: picOptions.map(v => ({ label: v, onClick: () => { this.updateForm('oo', v); this.setState({ openDropdown: null }); } })),
      previewWoNumber: '200-' + this.makeWoNumber().dateStr + '-' + this.makeWoNumber().seqStr,
      previewWoDate: new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }),
      briefingDateDisplay: this.state.form.hasBriefing === 'Yes' ? 'flex' : 'none',
      briefingDateDisplayCol: this.state.form.hasBriefing === 'Yes' ? 'flex' : 'none',
      onOpenRegister: () => this.openRegisterModal(),
      onCloseRegister: () => this.closeRegisterModal(),
      onSubmitRegister: () => this.submitRegisterTender(),
      onFormMode: (e) => this.updateForm('mode', e.target.value),
      onFormPic: (e) => this.updateForm('pic', e.target.value),
      onFormOo: (e) => this.updateForm('oo', e.target.value),
      onFormMinistry: (e) => this.updateForm('ministry', e.target.value),
      onFormQtNo: (e) => this.updateForm('qtNo', e.target.value),
      onFormQtTitle: (e) => this.updateForm('qtTitle', e.target.value),
      onFormPublishDate: (e) => this.updateForm('publishDate', e.target.value),
      onFormDeadline: (e) => this.updateForm('deadline', e.target.value),
      onFormValue: (e) => this.updateForm('value', e.target.value),
      onBriefingNo: () => this.updateForm('hasBriefing', 'No'),
      onBriefingYes: () => this.updateForm('hasBriefing', 'Yes'),
      briefingNoBg: this.state.form.hasBriefing === 'No' ? 'var(--accent)' : 'transparent',
      briefingNoColor: this.state.form.hasBriefing === 'No' ? '#fff' : 'var(--muted)',
      briefingYesBg: this.state.form.hasBriefing === 'Yes' ? 'var(--accent)' : 'transparent',
      briefingYesColor: this.state.form.hasBriefing === 'Yes' ? '#fff' : 'var(--muted)',
      onFormBriefingDate: (e) => this.updateForm('briefingDate', e.target.value),
      onFormType: (e) => this.updateForm('type', e.target.value),
      taskDocs, taskDone, taskTotal: taskDocs.length,
      newTaskName: this.state.newTaskName,
      onNewTaskNameChange: (e) => this.setState({ newTaskName: e.target.value }),
      onAddTaskDoc: isLocked ? (() => {}) : () => this.addTaskDoc(this.state.newTaskName),
      addDocDisplay: isLocked ? 'none' : 'flex',
      showBulkDocs: this.state.showBulkDocs,
      bulkDocsText: this.state.bulkDocsText,
      onBulkDocsTextChange: (e) => this.setState({ bulkDocsText: e.target.value }),
      onOpenBulkDocs: isLocked ? (() => {}) : () => this.openBulkDocs(),
      onCloseBulkDocs: () => this.closeBulkDocs(),
      onSubmitBulkDocs: () => this.submitBulkDocs(),
      showBulkImport: this.state.showBulkImport,
      bulkText: this.state.bulkText,
      onBulkTextChange: (e) => this.setState({ bulkText: e.target.value }),
      onCloseBulkImport: () => this.closeBulkImport(),
      onSubmitBulkImport: () => this.submitBulkImport(),
      viewingDoc: this.state.viewingDoc,
      onClosePreview: () => this.closeDocPreview(),
      stopClick: (e) => e.stopPropagation(),
      isLocked,
      onMarkDone: () => this.requestMarkDone(),
      showMarkDoneConfirm: this.state.showMarkDoneConfirm,
      markDoneWarning: this.state.markDoneWarning,
      onCancelMarkDone: () => this.setState({ showMarkDoneConfirm: false }),
      onConfirmMarkDone: () => this.confirmMarkDone(),
    };
  }
}
