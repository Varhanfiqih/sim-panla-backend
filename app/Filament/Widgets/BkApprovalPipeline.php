<?php

namespace App\Filament\Widgets;

use App\Models\Permission;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class BkApprovalPipeline extends BaseWidget
{
    protected static ?string $heading = 'Approval Pipeline BK';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Pengajuan izin yang menunggu verifikasi BK.')
            ->query(
                Permission::query()
                    ->with(['student.schoolClass', 'guru'])
                    ->where('status', 'pending')
                    ->latest()
            )
            ->poll('15s')
            ->columns([
                Tables\Columns\TextColumn::make('student.name')
                    ->label('Siswa')
                    ->description(fn (Permission $record): string => $record->student?->nis ?? '-')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('student.class_id')
                    ->label('Kelas')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'sakit' => 'danger',
                        'keluarga' => 'info',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('total_hari')
                    ->label('Durasi')
                    ->state(fn (Permission $record): string => $record->total_hari . ' hari'),
                Tables\Columns\TextColumn::make('guru.name')
                    ->label('Diajukan Oleh')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Diajukan')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (): string => 'Pending BK')
                    ->color('warning'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Detail')
                    ->modalHeading('Detail Pengajuan Izin')
                    ->form([
                        Forms\Components\Placeholder::make('student_name')
                            ->label('Siswa')
                            ->content(fn (Permission $record): string => $record->student?->name ?? '-'),
                        Forms\Components\Placeholder::make('class_id')
                            ->label('Kelas')
                            ->content(fn (Permission $record): string => $record->student?->class_id ?? '-'),
                        Forms\Components\Placeholder::make('type_label')
                            ->label('Jenis Izin')
                            ->content(fn (Permission $record): string => ucfirst($record->type)),
                        Forms\Components\Placeholder::make('date_range')
                            ->label('Tanggal')
                            ->content(fn (Permission $record): string => sprintf(
                                '%s - %s (%d hari)',
                                $record->start_date?->format('d M Y'),
                                $record->end_date?->format('d M Y'),
                                $record->total_hari,
                            )),
                        Forms\Components\Placeholder::make('requester')
                            ->label('Diajukan Oleh')
                            ->content(fn (Permission $record): string => $record->guru?->name ?? '-'),
                        Forms\Components\Placeholder::make('notes')
                            ->label('Keterangan')
                            ->content(fn (Permission $record): string => $record->keterangan ?: '-')
                            ->columnSpanFull(),
                    ]),
                Tables\Actions\Action::make('approveBk')
                    ->label('Approve BK')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Setujui Pengajuan Izin')
                    ->modalDescription('Status izin akan menjadi disetujui dan digunakan pada presensi siswa.')
                    ->action(function (Permission $record): void {
                        $record->update(['status' => 'approved']);

                        Notification::make()
                            ->title('Pengajuan disetujui')
                            ->body("Izin {$record->student?->name} telah disetujui.")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('rejectBk')
                    ->label('Tolak')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Tolak Pengajuan Izin')
                    ->modalDescription('Pengajuan akan ditandai ditolak dan tidak digunakan pada presensi siswa.')
                    ->action(function (Permission $record): void {
                        $record->update(['status' => 'rejected']);

                        Notification::make()
                            ->title('Pengajuan ditolak')
                            ->body("Izin {$record->student?->name} telah ditolak.")
                            ->danger()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Tidak ada approval yang menunggu')
            ->emptyStateDescription('Semua pengajuan izin sudah diproses oleh BK.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([10, 25, 50]);
    }
}
