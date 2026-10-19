@extends('layouts.admin-layout')

@section('title', 'Origens de acesso')

@section('page-title', 'Árvore de origens')

@section('content')
    <div class="admin-card">
        <div class="admin-card-header">
            <span>Origem vs URL</span>
            <span class="admin-muted text-xs font-normal">role para zoom · arraste para mover</span>
        </div>
        <div id="tree" class="h-[600px] w-full overflow-hidden"></div>
    </div>
@endsection

@push('scripts')
    <script type="module">
        // Deferred module: d3 comes from resources/js/admin.js.
        const treeData = {!! $treeData !!};
        const isDark = document.documentElement.classList.contains('dark');

        const palette = {
            bg: isDark ? '#09090b' : '#ffffff',
            stroke: isDark ? '#52525b' : '#d4d4d8',
            text: isDark ? '#e4e4e7' : '#3f3f46',
            parent: '#6366f1',
            leaf: '#10b981',
        };

        function paintTree() {
            const dark = document.documentElement.classList.contains('dark');
            palette.bg = dark ? '#09090b' : '#ffffff';
            palette.stroke = dark ? '#52525b' : '#d4d4d8';
            palette.text = dark ? '#e4e4e7' : '#3f3f46';
            d3.select('#tree svg').style('background-color', palette.bg);
            d3.selectAll('#tree .link').attr('stroke', palette.stroke);
            d3.selectAll('#tree .node text').style('fill', palette.text);
        }

        const width = document.getElementById('tree').offsetWidth;
        const height = document.getElementById('tree').offsetHeight;

        const svg = d3.select("#tree").append("svg")
            .attr("width", width)
            .attr("height", height)
            .style("background-color", palette.bg)
            .call(d3.zoom().on("zoom", function (event) {
                svg.attr("transform", event.transform);
            }))
            .append("g");

        const root = d3.hierarchy(treeData);

        const treeLayout = d3.tree().size([height, width - 160]);
        treeLayout(root);

        svg.selectAll('.link')
            .data(root.links())
            .enter()
            .append('line')
            .attr('class', 'link')
            .attr('x1', d => d.source.y)
            .attr('y1', d => d.source.x)
            .attr('x2', d => d.target.y)
            .attr('y2', d => d.target.x)
            .attr('stroke', palette.stroke)
            .attr('stroke-width', 1);

        const nodes = svg.selectAll('.node')
            .data(root.descendants())
            .enter()
            .append('g')
            .attr('class', 'node')
            .attr('transform', d => `translate(${d.y}, ${d.x})`);

        nodes.append('circle')
            .attr('r', 8)
            .attr('fill', d => d.children ? palette.parent : palette.leaf);

        nodes.append('text')
            .attr('dy', 3)
            .attr('x', d => d.children ? -12 : 12)
            .style('fill', palette.text)
            .text(d => d.data.name);

        // Create zoom behavior
        svg.call(d3.zoom().on("zoom", function(event) {
            svg.attr("transform", event.transform);
        }));

        document.addEventListener('admin-theme-changed', paintTree);
    </script>
@endpush
