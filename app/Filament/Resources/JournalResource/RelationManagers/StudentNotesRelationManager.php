<?php

namespace App\Filament\Resources\JournalResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Model;

class StudentNotesRelationManager extends RelationManager
{
    protected static string $relationship = 'studentNotes';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('student.name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student.name')
                    ->label('Nama Siswa')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('note_type')
                    ->label('Status Kehadiran')
                    ->badge()
                    ->formatStateUsing(fn ($state) => str_replace('KBM_', '', str_replace('_', ' ', $state)))
                    ->color(fn (string $state): string => match ($state) {
                        'KBM_Hadir' => 'success',
                        'KBM_Sakit_atau_Izin', 'KBM_Sakit', 'KBM_Izin' => 'warning',
                        'KBM_Alpa' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Catatan Guru'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Menonaktifkan pembuatan record baru di jurnal (Harus dari Apps Guru)
            ])
            ->actions([
                // Menonaktifkan aksi Edit/Delete di web admin untuk menjaga keaslian data Apps
            ])
            ->bulkActions([
                //
            ]);
    }
    
    public function isReadOnly(): bool
    {
        return true; // Paksa keseluruhan tabel menjadi read-only
    }
}
