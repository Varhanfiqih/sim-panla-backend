<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Guru & Staf';
    protected static ?string $navigationGroup = 'Manajemen';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Guru / Staf';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identitas')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('nip')
                        ->label('NIP')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(20),
                    Forms\Components\TextInput::make('name')
                        ->label('Nama Lengkap')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Select::make('role')
                        ->label('Role')
                        ->options(['Admin' => 'Admin', 'Guru' => 'Guru', 'Guru BK' => 'Guru BK'])
                        ->required(),
                    Forms\Components\TextInput::make('password')
                        ->label('Password')
                        ->password()
                        ->dehydrateStateUsing(fn($state) => Hash::make($state))
                        ->dehydrated(fn($state) => filled($state))
                        ->required(fn(string $operation): bool => $operation === 'create'),
                ]),
            Forms\Components\Section::make('Tugas Mengajar')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('subjects')
                        ->label('Mata Pelajaran yang Diampu')
                        ->relationship('subjects', 'name')
                        ->multiple()
                        ->preload()
                        ->columnSpanFull(),
                    Forms\Components\Select::make('classes')
                        ->label('Kelas Lingkup Ajar')
                        ->relationship('classes', 'id')
                        ->multiple()
                        ->preload()
                        ->columnSpanFull(),
                    Forms\Components\Select::make('homeroomClass')
                        ->label('Wali Kelas dari')
                        ->relationship('homeroomClass', 'id')
                        ->helperText('Kosongkan jika bukan wali kelas'),
                    Forms\Components\Toggle::make('is_inval_piket')
                        ->label('Guru Piket / Inval')
                        ->default(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nip')->label('NIP')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('role')
                    ->colors(['primary' => 'Admin', 'success' => 'Guru', 'warning' => 'Guru BK']),
                Tables\Columns\TextColumn::make('homeroomClass.id')->label('Wali Kelas')->placeholder('—')->sortable(),
                Tables\Columns\IconColumn::make('is_inval_piket')->label('Piket')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('Dibuat')->date('d M Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')->options(['Admin' => 'Admin', 'Guru' => 'Guru', 'Guru BK' => 'Guru BK']),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageUsers::route('/'),
        ];
    }
}
