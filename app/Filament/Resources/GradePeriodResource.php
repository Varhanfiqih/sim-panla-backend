<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GradePeriodResource\Pages;
use App\Filament\Resources\GradePeriodResource\RelationManagers;
use App\Models\GradePeriod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class GradePeriodResource extends Resource
{
    protected static ?string $model = GradePeriod::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationGroup = 'Penilaian';
    protected static ?string $navigationLabel = 'Periode Nilai';
    protected static ?string $modelLabel = 'Periode Nilai';
    protected static ?string $pluralModelLabel = 'Periode Nilai';
    protected static ?int $navigationSort = 2;

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
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Periode')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('academic_year')
                            ->label('Tahun Ajaran')
                            ->placeholder('2026/2027')
                            ->mask('9999/9999')
                            ->regex('/^\d{4}\/\d{4}$/')
                            ->validationMessages([
                                'regex' => 'Tahun ajaran harus menggunakan format 2026/2027.',
                            ])
                            ->required(),
                        Forms\Components\Select::make('semester')
                            ->label('Semester')
                            ->options([
                                'ganjil' => 'Ganjil',
                                'genap' => 'Genap',
                            ])
                            ->native(false)
                            ->required(),
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Tanggal Mulai')
                            ->native(false)
                            ->required(),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('Tanggal Selesai')
                            ->native(false)
                            ->afterOrEqual('start_date')
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Jadikan Periode Aktif')
                            ->helperText('Jika aktif, periode ini digunakan sebagai periode penilaian berjalan.')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Periode')->searchable(),
                Tables\Columns\TextColumn::make('academic_year')
                    ->label('Tahun Ajaran')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('semester')
                    ->label('Semester')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'ganjil' => 'Ganjil',
                        'genap' => 'Genap',
                        default => '-',
                    })
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'ganjil' => 'warning',
                        'genap' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
                Tables\Columns\TextColumn::make('start_date')->label('Mulai')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('end_date')->label('Selesai')->date('d M Y')->sortable(),
            ])
            ->filters([
                //
            ])
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
            'index' => Pages\ListGradePeriods::route('/'),
            'create' => Pages\CreateGradePeriod::route('/create'),
            'edit' => Pages\EditGradePeriod::route('/{record}/edit'),
        ];
    }
}
