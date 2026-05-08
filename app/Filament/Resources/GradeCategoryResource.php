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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class GradeCategoryResource extends Resource
{
    protected static ?string $model = GradeCategory::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Penilaian';
    protected static ?string $navigationLabel = 'Kategori Nilai';
    protected static ?string $modelLabel = 'Kategori Nilai';
    protected static ?string $pluralModelLabel = 'Kategori Nilai';
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
                    ->label('Nama Kategori')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('weight')
                    ->label('Bobot (%)')
                    ->numeric()
                    ->default(100)
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->label('Keterangan')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Kategori')->searchable(),
                Tables\Columns\TextColumn::make('weight')->label('Bobot (%)')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('description')->label('Keterangan')->limit(50),
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
            'index' => Pages\ListGradeCategories::route('/'),
            'create' => Pages\CreateGradeCategory::route('/create'),
            'edit' => Pages\EditGradeCategory::route('/{record}/edit'),
        ];
    }
}
