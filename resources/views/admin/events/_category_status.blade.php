<form method="POST" action="{{ route('admin.categories.status', $category) }}" class="d-inline">
    @csrf @method('PATCH')
    @if($category->status !== 'open')
        <button name="status" value="open" class="btn btn-xs btn-primary" title="Allow judges to score"><i class="fa fa-unlock"></i> Open</button>
    @endif
    @if($category->status === 'open')
        <button name="status" value="locked" class="btn btn-xs btn-danger" title="Stop scoring"><i class="fa fa-lock"></i> Lock</button>
    @endif
    @if($category->status === 'locked')
        <button name="status" value="pending" class="btn btn-xs btn-white" title="Set back to pending"><i class="fa fa-undo"></i></button>
    @endif
</form>
