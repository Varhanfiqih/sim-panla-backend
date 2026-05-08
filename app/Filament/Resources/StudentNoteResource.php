<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StudentNoteResource\Pages;
use App\Filament\Resources\StudentNoteResource\RelationManagers;
use App\Models\StudentNote;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StudentNoteResource extends Resource
{
    protected static ?string $model = StudentNote::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Operasional';
    protected static ?string $navigationLabel = 'Izin Siswa';
    protected static ?string $modelLabel = 'Izin Siswa';
    protected static ?string $pluralModelLabel = 'Izin Siswa';
    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user?->isStaff() || $user?->isKepsek();
    }

    public static function canCreate(): bool { return auth()->user()?->isStaff() ?? false; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return auth()->user()?->isStaff() ?? false; }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return auth()->user()?->isStaff() ?? false; }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('student_id')
                    ->label('Siswa')
                    ->relationship('student', 'name')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('note_type')
                    ->label('Jenis Catatan')
                    ->options([
                        'KBM_Sakit_atau_Izin' => 'Sakit / Izin',
                        'KBM_Alpa' => 'Alpa',
                        'KBM_Hadir' => 'Hadir',
                    ])
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->label('Keterangan')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student.name')->label('Siswa')->searchable(),
                Tables\Columns\TextColumn::make('note_type')->label('Jenis')->badge(),
                Tables\Columns\TextColumn::make('notes')->label('Keterangan'),
                Tables\Columns\TextColumn::make('created_at')->label('Tanggal')->date('d M Y'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudentNotes::route('/'),
            'create' => Pages\CreateStudentNote::route('/create'),
            'edit' => Pages\EditStudentNote::route('/{record}/edit'),
        ];
    }
}
