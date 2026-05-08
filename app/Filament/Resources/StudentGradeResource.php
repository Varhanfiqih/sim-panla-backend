<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StudentGradeResource\Pages;
use App\Filament\Resources\StudentGradeResource\RelationManagers;
use App\Models\StudentGrade;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StudentGradeResource extends Resource
{
    protected static ?string $model = StudentGrade::class;
    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';
    protected static ?string $navigationGroup = 'Penilaian';
    protected static ?string $navigationLabel = 'Nilai Siswa';
    protected static ?string $modelLabel = 'Nilai Siswa';
    protected static ?string $pluralModelLabel = 'Nilai Siswa';
    protected static ?int $navigationSort = 3;

    // ─── Otorisasi Resource ───────────────────────────────────────────────────

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user?->isStaff() || $user?->isKepsek();
    }

    public static function canCreate(): bool { return auth()->user()?->isStaff() ?? false; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return auth()->user()?->isStaff() ?? false; }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return auth()->user()?->isStaff() ?? false; }
    public static function canDeleteAny(): bool { return auth()->user()?->isStaff() ?? false; }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('student_nisn')
                    ->label('Siswa')
                    ->relationship('student', 'name')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('subject_id')
                    ->label('Mata Pelajaran')
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('grade_category_id')
                    ->label('Kategori Nilai')
                    ->relationship('gradeCategory', 'name')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('grade_period_id')
                    ->label('Periode Nilai')
                    ->relationship('gradePeriod', 'name')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('score')
                    ->label('Nilai')
                    ->numeric()
                    ->inputMode('decimal')
                    ->minValue(0)
                    ->maxValue(100)
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->label('Catatan Khusus')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student.name')->label('Siswa')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('subject.name')->label('Mata Pelajaran')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('gradeCategory.name')->label('Kategori')->sortable(),
                Tables\Columns\TextColumn::make('gradePeriod.name')->label('Periode')->sortable(),
                Tables\Columns\TextColumn::make('score')->label('Nilai')->numeric(2)->sortable(),
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
            'index' => Pages\ListStudentGrades::route('/'),
            'create' => Pages\CreateStudentGrade::route('/create'),
            'edit' => Pages\EditStudentGrade::route('/{record}/edit'),
        ];
    }
}
