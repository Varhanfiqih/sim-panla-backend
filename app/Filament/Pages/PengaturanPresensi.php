<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use App\Models\AppSetting;
use Filament\Notifications\Notification;

class PengaturanPresensi extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-8-tooth';
    protected static ?string $navigationLabel = 'Pengaturan Presensi';
    protected static ?string $navigationGroup = 'Sistem';
    protected static ?int $navigationSort = 4;
    protected static ?string $title = 'Aturan Absensi Sekolah';
    protected static string $view = 'filament.pages.pengaturan-presensi';

    // ─── Otorisasi Resource ───────────────────────────────────────────────────

    /** Super Admin dan Admin IT bisa mengakses pengaturan presensi */
    public static function canAccess(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    public ?array $data = [];

    public function mount(): void
    {
        // Memastikan konfigurasi core selalu ada (juga saat instalasi bersih / db reset)
        $defaults = [
            'jam_buka_gerbang' => '05:00',
            'jam_masuk' => '07:00',
            'jam_terlambat_toleransi' => '07:15',
            'jam_pulang' => '15:00',
            'batas_jam_pulang' => '16:30',
        ];

        foreach ($defaults as $key => $val) {
            AppSetting::firstOrCreate(
                ['key' => $key], 
                ['value' => $val, 'label' => ucwords(str_replace('_', ' ', $key)), 'type' => 'time']
            );
        }

        // Tipe Kategori Hadir/Scan Spesifik
        $typesSetting = AppSetting::where('key', 'tipe_presensi_custom')->first();
        if (!$typesSetting) {
            $typesSetting = AppSetting::create([
                'key' => 'tipe_presensi_custom',
                'value' => json_encode([
                    ['kode' => 'Hadir', 'label' => 'Gerbang: Hadir Tepat Waktu'],
                    ['kode' => 'Terlambat', 'label' => 'Gerbang: Terlambat'],
                    ['kode' => 'Izin_Pulang', 'label' => 'Pos/BK: Izin Pulang Awal'],
                    ['kode' => 'KBM_Hadir', 'label' => 'Jurnal Mapel: Hadir'],
                    ['kode' => 'KBM_Alpa', 'label' => 'Jurnal Mapel: Alpa/Tanpa Keterangan']
                ]),
                'label' => 'Master Tipe Presensi',
                'type' => 'json'
            ]);
        }
        
        $settings = AppSetting::pluck('value', 'key')->toArray();
        $this->form->fill([
            'jam_buka_gerbang' => substr($settings['jam_buka_gerbang'] ?? '05:00', 0, 5),
            'jam_masuk' => substr($settings['jam_masuk'] ?? '07:00', 0, 5),
            'jam_terlambat_toleransi' => substr($settings['jam_terlambat_toleransi'] ?? '07:15', 0, 5),
            'jam_pulang' => substr($settings['jam_pulang'] ?? '15:00', 0, 5),
            'batas_jam_pulang' => substr($settings['batas_jam_pulang'] ?? '16:30', 0, 5),
            'tipe_presensi_custom' => isset($settings['tipe_presensi_custom']) ? json_decode($settings['tipe_presensi_custom'], true) : [],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Ketentuan Waktu KBM')
                    ->description('Sesuaikan rentang waktu gerbang, jam masuk normal, batas toleransi agar sistem otomasi menandai siswa "Terlambat".')
                    ->columns(2)
                    ->schema([
                        TimePicker::make('jam_buka_gerbang')
                            ->label('Jam Buka Gerbang Awal')
                            ->seconds(false)
                            ->required(),
                        TimePicker::make('jam_masuk')
                            ->label('Batas Jam Masuk Normal')
                            ->seconds(false)
                            ->required(),
                        TimePicker::make('jam_terlambat_toleransi')
                            ->label('Batas Maksimal Toleransi Keterlambatan')
                            ->seconds(false)
                            ->required(),
                        TimePicker::make('jam_pulang')
                            ->label('Jam Mulai Pulang')
                            ->seconds(false)
                            ->required(),
                        TimePicker::make('batas_jam_pulang')
                            ->label('Batas Akhir Gerbang Ditutup / Checkout')
                            ->seconds(false)
                            ->required(),
                    ]),
                    
                Section::make('Jenis Pengkategorian Absen (Customable Types)')
                    ->description('Daftar Label Status Absensi yang muncul sebagai Pilihan pada Aplikasi Mobile Admin / Guru Piket.')
                    ->schema([
                        Repeater::make('tipe_presensi_custom')
                            ->label('Jenis Absensi/Presensi Tambahan')
                            ->schema([
                                TextInput::make('kode')
                                    ->label('Sandi / ID Internal')
                                    ->helperText('Unik per tipe, misal: Ekstra_Pramuka')
                                    ->required(),
                                TextInput::make('label')
                                    ->label('Label Visual (Di Aplikasi)')
                                    ->helperText('Misal: Kehadiran Ekstrakurikuler Wajib')
                                    ->required(),
                            ])
                            ->columns(2)
                            ->addActionLabel('Tambahkan Tipe Scan Baru')
                            ->reorderableWithButtons(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $timeKeys = ['jam_buka_gerbang', 'jam_masuk', 'jam_terlambat_toleransi', 'jam_pulang', 'batas_jam_pulang'];
        foreach ($timeKeys as $key) {
            AppSetting::updateOrCreate(['key' => $key], ['value' => $data[$key]]);
        }

        AppSetting::updateOrCreate(
            ['key' => 'tipe_presensi_custom'], 
            ['value' => json_encode($data['tipe_presensi_custom']), 'type' => 'json']
        );

        Notification::make()
            ->title('Konfigurasi Jadwal & Aturan Absensi Berhasil Diperbarui.')
            ->success()
            ->send();
    }
}
