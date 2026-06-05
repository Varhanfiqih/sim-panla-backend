<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StudentGradeResource\Pages;
use App\Filament\Resources\StudentGradeResource\RelationManagers;
use App\Models\GradeCategory;
use App\Models\GradePeriod;
use App\Models\SchoolClass;
use App\Models\StudentGrade;
use App\Models\Subject;
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

    public static function canCreate(): bool { return auth()->user()?->isSuperAdmin() ?? false; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return auth()->user()?->isSuperAdmin() ?? false; }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return auth()->user()?->isSuperAdmin() ?? false; }
    public static function canDeleteAny(): bool { return auth()->user()?->isSuperAdmin() ?? false; }

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
                Forms\Components\TextInput::make('item_no')
                    ->label('Item Ke')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
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
                Tables\Columns\TextColumn::make('student.nis')
                    ->label('NIS')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('subject.name')->label('Mata Pelajaran')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('gradeCategory.name')->label('Kategori')->sortable(),
                Tables\Columns\TextColumn::make('item_no')->label('Item Ke')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('gradePeriod.name')->label('Periode')->sortable(),
                Tables\Columns\TextColumn::make('score')->label('Nilai')->numeric(2)->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('grade_period_id')
                    ->label('Periode')
                    ->options(fn (): array => GradePeriod::query()
                        ->orderByDesc('is_active')
                        ->orderByDesc('start_date')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('class_id')
                    ->label('Kelas')
                    ->options(fn (): array => SchoolClass::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value']),
                        fn (Builder $query): Builder => $query->whereHas(
                            'student',
                            fn (Builder $studentQuery): Builder => $studentQuery->where('class_id', $data['value']),
                        ),
                    ))
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('subject_id')
                    ->label('Mapel')
                    ->options(fn (): array => Subject::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('grade_category_id')
                    ->label('Kategori')
                    ->options(fn (): array => GradeCategory::query()
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ])->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
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
