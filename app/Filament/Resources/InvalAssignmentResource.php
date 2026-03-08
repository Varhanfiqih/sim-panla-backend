<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvalAssignmentResource\Pages;
use App\Filament\Resources\InvalAssignmentResource\RelationManagers;
use App\Models\InvalAssignment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InvalAssignmentResource extends Resource
{
    protected static ?string $model = InvalAssignment::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $modelLabel = 'Penugasan Inval';

    protected static ?string $pluralModelLabel = 'Daftar Penugasan Inval';

    protected static ?string $navigationGroup = 'Manajemen Jurnal';
    
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('schedule_id')
                    ->label('Jadwal Asli')
                    ->relationship('schedule', 'id')
                    ->required(),
                Forms\Components\Select::make('replacement_teacher_id')
                    ->label('Guru Pengganti')
                    ->relationship('replacementTeacher', 'name')
                    ->required(),
                Forms\Components\DatePicker::make('date')
                    ->label('Tanggal Inval')
                    ->default(now())
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'assigned' => 'Assigned (Oleh Admin)',
                        'claimed' => 'Claimed (Lewat App)',
                        'completed' => 'Completed',
                    ])
                    ->default('assigned')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('schedule.subject.name')
                    ->label('Mata Pelajaran')
                    ->sortable(),
                Tables\Columns\TextColumn::make('schedule.schoolClass.name')
                    ->label('Kelas')
                    ->sortable(),
                Tables\Columns\TextColumn::make('replacementTeacher.name')
                    ->label('Guru Pengganti')
                    ->searchable(),
                Tables\Columns\TextColumn::make('date')
                    ->label('Tanggal')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'assigned' => 'warning',
                        'claimed' => 'success',
                        'completed' => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListInvalAssignments::route('/'),
            'create' => Pages\CreateInvalAssignment::route('/create'),
            'edit' => Pages\EditInvalAssignment::route('/{record}/edit'),
        ];
    }
}
