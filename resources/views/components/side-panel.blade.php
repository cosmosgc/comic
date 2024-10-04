<!-- resources/views/components/side-panel.blade.php -->
<div class="side-panel bg-light shadow-sm p-4 {{ $position ?? 'left' }}-panel">
    <h4>{{ $title ?? 'Side Panel' }}</h4>
    <div class="side-panel-content">
        {{ $slot }}
    </div>
</div>

<!-- Add custom styling for side panel positioning -->
<style>
    .left-panel {
        position: fixed;
        left: 0;
        top: 0;
        bottom: 0;
        width: 250px;
        z-index: 1000;
    }

    .right-panel {
        position: fixed;
        right: 0;
        top: 0;
        bottom: 0;
        width: 250px;
        z-index: 1000;
    }

    .side-panel {
        background-color: #f8f9fa;
        height: 100%;
        padding-top: 60px;
        overflow-y: auto;
    }
</style>
