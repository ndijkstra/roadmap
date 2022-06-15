<?php

namespace App\Http\Livewire\Modals\Item;

use Closure;
use function app;
use function auth;
use function view;
use function route;
use App\Models\Item;
use function redirect;
use App\Models\Project;
use App\Settings\GeneralSettings;
use Filament\Forms\Components\Group;
use LivewireUI\Modal\ModalComponent;
use Filament\Forms\Components\Select;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Http\Livewire\Concerns\CanNotify;
use Filament\Forms\Concerns\InteractsWithForms;

class CreateItemModal extends ModalComponent implements HasForms
{
    use InteractsWithForms, CanNotify;

    public function mount()
    {
        $this->form->fill([]);
        $this->similarItems = [];
    }

    protected function getFormSchema(): array
    {
        $inputs = [];

        $inputs[] = TextInput::make('title')
            ->label(trans('table.title'))
            ->lazy()
            ->afterStateUpdated(function (Closure $set, $state) {
                $set('similarItems', $state ? Item::where('title','LIKe','%'.$state.'%')->get(['title','slug']) : []);
            })
            ->minLength(3)
            ->required();

        if (app(GeneralSettings::class)->select_project_when_creating_item) {
            $inputs[] = Select::make('project_id')
                ->label(trans('table.project'))
                ->reactive()
                ->options(Project::query()->pluck('title', 'id'))
                ->required(app(GeneralSettings::class)->project_required_when_creating_item);
        }

        if (app(GeneralSettings::class)->select_board_when_creating_item) {
            $inputs[] = Select::make('board_id')
                ->label(trans('table.board'))
                ->visible(fn ($get) => $get('project_id'))
                ->options(fn ($get) => Project::find($get('project_id'))->boards()->pluck('title', 'id'))
                ->required(app(GeneralSettings::class)->board_required_when_creating_item);
        }

        $inputs[] = Group::make([
            MarkdownEditor::make('content')
                ->label(trans('table.content'))
                ->minLength(10)
                ->required()
        ]);

        return $inputs;
    }

    public function submit()
    {
        if (!auth()->user()) {
            return redirect()->route('login');
        }

        $data = $this->form->getState();

        $item = Item::create([
            'title' => $data['title'],
            'content' => $data['content'],
            'project_id' => $data['project_id'] ?? null
        ]);

        $item->user()->associate(auth()->user())->save();

        $item->toggleUpvote();

        $this->closeModal();

        $this->notify('success', trans('items.item_created'));

        return route('items.show', $item->id);
    }

    public function render()
    {
        return view('livewire.modals.create-item-modal');
    }

    public static function closeModalOnClickAway(): bool
    {
        return false;
    }

    public static function closeModalOnEscape(): bool
    {
        return false;
    }
}