<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JournalResource\Pages;
use App\Filament\Resources\JournalResource\RelationManagers;
use App\Models\Journal;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class JournalResource extends Resource
{
    protected static ?string $model = Journal::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Jurnal Mengajar';
    protected static ?string $navigationGroup = 'Laporan';
    protected static ?string $pluralModelLabel = 'Daftar Jurnal Mengajar';
    protected static ?int $navigationSort = 2; // Tepat di bawah Rekap Presensi (1)

    // ─── Otorisasi Resource ───────────────────────────────────────────────────

    /** Laporan jurnal hanya bisa dilihat oleh Kepala Sekolah (dan Super Admin). Admin IT tidak bisa. */
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user?->isKepsek() || $user?->isSuperAdmin();
    }

    /** Jurnal tidak bisa dibuat manual dari Filament (hanya dari aplikasi guru). */
    public static function canCreate(): bool { return false; }

    /** Hanya Super Admin yang bisa edit jurnal (untuk koreksi data). */
    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    /** Hanya Super Admin yang bisa hapus jurnal. */
    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Rincian Jurnal Mengajar')
                    ->description('Informasi lengkap presensi dan kegiatan mengajar guru.')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Placeholder::make('teacher_name')
                                    ->label('Nama Guru')
                                    ->content(fn ($record) => $record?->teacher?->name ?? '-'),
                               Forms\Components\Placeholder::make('created_at_time')
                                    ->label('Waktu Pengisian')
                                    ->content(fn ($record) => $record?->created_at?->format('d M Y, H:i') ?? '-'),
                                Forms\Components\Placeholder::make('subject_name')
                                    ->label('Mata Pelajaran')
                                    ->content(fn ($record) => $record?->schedule?->subject?->name ?? 'Inval'),
                                Forms\Components\Placeholder::make('class_name')
                                    ->label('Kelas')
                                    ->content(fn ($record) => $record?->schedule?->schoolClass?->name ?? '-'),
                            ]),
                            
                        Forms\Components\Textarea::make('material')
                            ->label('Materi Pembelajaran')
                            ->disabled()
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('attachment_preview')
                            ->label('Lampiran')
                            ->content(function (?Journal $record): HtmlString|string {
                                if (! $record?->attachment_path) {
                                    return 'Tidak ada lampiran.';
                                }

                                $url = route('admin.journals.attachment.show', $record, false);
                                $fileName = basename($record->attachment_path);
                                $extension = strtolower(pathinfo($record->attachment_path, PATHINFO_EXTENSION));
                                $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true);

                                if ($isImage) {
                                    return new HtmlString(sprintf(
                                        '<a href="%s" target="_blank" rel="noopener noreferrer">
                                            <img src="%s" alt="%s" style="max-height: 260px; max-width: 100%%; border-radius: 6px; object-fit: contain;">
                                        </a>
                                        <div style="margin-top: 8px;">
                                            <a href="%s" target="_blank" rel="noopener noreferrer">Lihat ukuran penuh</a>
                                        </div>',
                                        e($url),
                                        e($url),
                                        e($fileName),
                                        e($url),
                                    ));
                                }

                                return new HtmlString(sprintf(
                                    '<a href="%s" target="_blank" rel="noopener noreferrer">Lihat / Unduh %s</a>',
                                    e($url),
                                    e($fileName),
                                ));
                            })
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('cleanliness')
                                    ->label('Kondisi Kebersihan')
                                    ->formatStateUsing(fn ($state) => $state === 'sudah_bersih' ? 'Bersih & Rapi' : 'Kotor/Berantakan')
                                    ->disabled()
                                    ->prefixIcon(fn ($state) => $state === 'sudah_bersih' ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle')
                                    ->prefixIconColor(fn ($state) => $state === 'sudah_bersih' ? 'success' : 'danger'),
                                Forms\Components\Toggle::make('is_inval')
                                    ->label('Status Inval')
                                    ->disabled(),
                            ]),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu Diisi')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('teacher.name')
                    ->label('Nama Guru')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('schedule.subject.name')
                    ->label('Mata Pelajaran')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('schedule.schoolClass.name')
                    ->label('Kelas')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('material')
                    ->label('Materi')
                    ->limit(20)
                    ->searchable(),
                Tables\Columns\TextColumn::make('attachment_path')
                    ->label('Lampiran')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? 'Download' : '-')
                    ->icon(fn (?string $state): ?string => filled($state) ? 'heroicon-m-arrow-down-tray' : null)
                    ->color(fn (?string $state): string => filled($state) ? 'primary' : 'gray')
                    ->url(fn (Journal $record): ?string => $record->attachment_path
                        ? route('admin.journals.attachment.download', $record, false)
                        : null)
                    ->openUrlInNewTab(),
                Tables\Columns\TextColumn::make('cleanliness')
                    ->label('Kebersihan')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'sudah_bersih' ? 'Bersih' : 'Kotor')
                    ->color(fn ($state) => $state === 'sudah_bersih' ? 'success' : 'danger'),
                Tables\Columns\IconColumn::make('is_inval')
                    ->label('Inval')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
            ])
            ->filters([
                Tables\Filters\Filter::make('created_at')
                    ->label('Filter Tanggal')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->icon('heroicon-m-eye')
                    ->color('primary'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Nonaktifkan bulk delete demi integritas data laporan KBM
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\StudentNotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJournals::route('/'),
            // 'create' => Pages\CreateJournal::route('/create'), // Disable Create via Web
            'edit' => Pages\EditJournal::route('/{record}/edit'), // Kembali memakai template edit, karena fields sudah di set disable di atas.
        ];
    }
}
