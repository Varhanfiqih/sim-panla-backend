<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GradeCategoryResource\Pages;
use App\Filament\Resources\GradeCategoryResource\RelationManagers;
use App\Models\GradeCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GradeCategoryResource extends Resource
{
    protected static ?string $model = GradeCategory::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Penilaian';
    protected static ?string $navigationLabel = 'Kategori Nilai';
    protected static ?string $modelLabel = 'Kategori Nilai';
    protected static ?string $pluralModelLabel = 'Kategori Nilai';
    protected static ?string $slug = 'assessment-categories';
    protected static ?int $navigationSort = 1;

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
                Forms\Components\TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('code')
                    ->label('Kode')
                    ->required()
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->maxLength(100),
                Forms\Components\Toggle::make('is_repeatable')
                    ->label('Multi Item')
                    ->helperText('Aktifkan untuk kategori seperti Ulangan Harian 1-5.')
                    ->live()
                    ->default(false),
                Forms\Components\TextInput::make('max_item')
                    ->label('Maks Item')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->required(),
                Forms\Components\TextInput::make('max_score')
                    ->label('Nilai Maks')
                    ->numeric()
                    ->minValue(1)
                    ->default(100)
                    ->required(),
                Forms\Components\TextInput::make('weight')
                    ->label('Bobot')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(0)
                    ->required(),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Urutan')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
                Forms\Components\Textarea::make('description')
                    ->label('Keterangan')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_repeatable')
                    ->label('Multi')
                    ->boolean(),
                Tables\Columns\TextColumn::make('max_item')
                    ->label('Maks Item')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_score')
                    ->label('Nilai Maks')
                    ->numeric(decimalPlaces: 0)
                    ->sortable(),
                Tables\Columns\TextColumn::make('weight')
                    ->label('Bobot')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Urutan')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->isStaff() ?? false),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()?->isStaff() ?? false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ])->visible(fn (): bool => auth()->user()?->isStaff() ?? false),
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
            'index' => Pages\ListGradeCategories::route('/'),
            'create' => Pages\CreateGradeCategory::route('/create'),
            'edit' => Pages\EditGradeCategory::route('/{record}/edit'),
        ];
    }
}
