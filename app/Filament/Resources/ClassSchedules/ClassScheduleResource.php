<?php

namespace App\Filament\Resources\ClassSchedules;

use App\Filament\Resources\ClassSchedules\Pages\CreateClassSchedule;
use App\Filament\Resources\ClassSchedules\Pages\EditClassSchedule;
use App\Filament\Resources\ClassSchedules\Pages\ListClassSchedules;
use App\Models\ClassSchedule;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class ClassScheduleResource extends Resource
{
    protected static ?string $model = ClassSchedule::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|UnitEnum|null $navigationGroup = 'Clases';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Clase del horario';

    protected static ?string $pluralModelLabel = 'Horario semanal';

    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole(['admin', 'empleado']);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasRole('admin');
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->hasRole('admin');
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->hasRole('admin');
    }

    /** Mismas horas que maneja la configuración de la sucursal (06:00 a 21:00). */
    public static function timeOptions(): array
    {
        $hours = [];
        for ($i = 6; $i <= 21; $i++) {
            $time = sprintf('%02d:00', $i);
            $hours[$time] = date('h:i A', strtotime($time));
        }

        return $hours;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Clase fija de la semana')
                ->description('Al guardar, esta hora se agrega automáticamente a los horarios disponibles para reservar.')
                ->schema([
                    Select::make('day_of_week')->label('Día')->options(ClassSchedule::DAYS)->required(),
                    Select::make('start_time')->label('Hora')->options(self::timeOptions())->required()
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, callable $get) => $rule
                            ->where('tenant_id', Filament::getTenant()?->id)
                            ->where('day_of_week', $get('day_of_week')))
                        ->validationMessages(['unique' => 'Ya hay una clase registrada ese día a esa hora.']),
                    Select::make('discipline_id')->label('Disciplina')
                        ->relationship('discipline', 'name', fn (Builder $query) => $query->where('tenant_id', Filament::getTenant()?->id)->where('is_active', true))
                        ->preload()->required(),
                    Select::make('instructor_id')->label('Instructor')
                        ->relationship('instructor', 'name', fn (Builder $query) => $query->where('tenant_id', Filament::getTenant()?->id)->where('is_active', true))
                        ->preload(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('day_of_week')->label('Día')
                    ->formatStateUsing(fn (int $state): string => ClassSchedule::DAYS[$state] ?? '')
                    ->sortable(),
                TextColumn::make('start_time')->label('Hora')->sortable()
                    ->formatStateUsing(fn (string $state): string => date('h:i A', strtotime($state))),
                TextColumn::make('discipline.name')->label('Disciplina')->badge()->color('primary'),
                TextColumn::make('instructor.name')->label('Instructor')->placeholder('Sin asignar'),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('day_of_week')->orderBy('start_time'))
            ->filters([
                SelectFilter::make('day_of_week')->label('Día')->options(ClassSchedule::DAYS),
                SelectFilter::make('discipline_id')->label('Disciplina')->relationship('discipline', 'name'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClassSchedules::route('/'),
            'create' => CreateClassSchedule::route('/create'),
            'edit' => EditClassSchedule::route('/{record}/edit'),
        ];
    }
}
