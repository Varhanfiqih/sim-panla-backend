<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StudentResource\Pages;
use App\Models\Student;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Siswa';
    protected static ?string $navigationGroup = 'Manajemen';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'Siswa';

    public static function form(Form $form): Form
    {


        return $form->schema([
            Forms\Components\Section::make('Data Siswa')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('nisn')
                        ->label('NISN')
                        ->unique(ignoreRecord: true)
                        ->maxLength(20),
                    Forms\Components\TextInput::make('nis')
                        ->label('NIS')
                        ->unique(ignoreRecord: true)
                        ->maxLength(20),
                    Forms\Components\TextInput::make('name')
                        ->label('Nama Lengkap')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Forms\Components\Select::make('class_id')
                        ->label('Kelas')
                        ->relationship('schoolClass', 'id')
                        ->required()
                        ->searchable(),
                    Forms\Components\Select::make('gender')
                        ->label('Jenis Kelamin')
                        ->options(['L' => 'Laki-laki', 'P' => 'Perempuan']),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nisn')->label('NISN')->searchable(),
                Tables\Columns\TextColumn::make('nis')->label('NIS')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('class_id')->label('Kelas')->sortable(),
                Tables\Columns\BadgeColumn::make('gender')->label('JK')
                    ->colors(['primary' => 'L', 'pink' => 'P']),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('class_id')
                    ->relationship('schoolClass', 'id')
                    ->label('Filter Kelas'),
            ])
            ->defaultSort('class_id')
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
            'index' => Pages\ManageStudents::route('/'),
        ];
    }
}
