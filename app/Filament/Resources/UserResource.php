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
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Guru & Staf';
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Guru / Staf';
    protected static ?string $pluralModelLabel = 'Guru / Staf';

    // ─── Otorisasi Resource ───────────────────────────────────────────────────

    /**
     * Semua role (Super Admin, Admin IT, Kepala Sekolah) bisa melihat daftar Guru & Staf.
     * Namun hanya Super Admin yang bisa menambah/mengedit data akun.
     */
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user?->isStaff() || $user?->isKepsek();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    // ─── Form ─────────────────────────────────────────────────────────────────

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
                        ->options([
                            User::ROLE_SUPER_ADMIN    => 'Super Admin',
                            User::ROLE_ADMIN_IT       => 'Admin IT',
                            User::ROLE_KEPALA_SEKOLAH => 'Kepala Sekolah',
                            User::ROLE_GURU           => 'Guru',
                            User::ROLE_GURU_BK        => 'Guru BK',
                        ])
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
                ->visible(fn(?string $operation, ?Model $record) =>
                    // Hanya tampil untuk role Guru & Guru BK
                    in_array($record?->role ?? '', [User::ROLE_GURU, User::ROLE_GURU_BK])
                )
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

    // ─── Table ────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nip')->label('NIP')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('role')
                    ->colors([
                        'danger'  => User::ROLE_SUPER_ADMIN,
                        'warning' => User::ROLE_ADMIN_IT,
                        'info'    => User::ROLE_KEPALA_SEKOLAH,
                        'success' => User::ROLE_GURU,
                        'gray'    => User::ROLE_GURU_BK,
                    ]),
                Tables\Columns\TextColumn::make('homeroomClass.id')->label('Wali Kelas')->placeholder('—')->sortable(),
                Tables\Columns\IconColumn::make('is_inval_piket')->label('Piket')->boolean(),
                Tables\Columns\TextColumn::make('password_changed_at')
                    ->label('Password Terakhir Diubah')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('Belum tercatat')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Dibuat')->date('d M Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')->options([
                    User::ROLE_SUPER_ADMIN    => 'Super Admin',
                    User::ROLE_ADMIN_IT       => 'Admin IT',
                    User::ROLE_KEPALA_SEKOLAH => 'Kepala Sekolah',
                    User::ROLE_GURU           => 'Guru',
                    User::ROLE_GURU_BK        => 'Guru BK',
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->using(function (Model $record, array $data): Model {
                        $record->fill([
                            'nip' => $data['nip'],
                            'name' => $data['name'],
                            'role' => $data['role'],
                            'is_inval_piket' => $data['is_inval_piket'] ?? false,
                        ]);

                        if (filled($data['password'] ?? null)) {
                            $record->password = $data['password'];
                        }

                        $record->save();

                        return $record;
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                // Hanya Super Admin yang bisa bulk delete (via canDelete check di atas)
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
