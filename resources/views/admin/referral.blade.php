@extends('layouts.admin-layout')

@section('title', 'Referral Analytics')

@section('page-title', 'Referral Source vs URL Tree')

@section('content')
    <div id="tree" style="width: 100%; height: 600px; background-color: #121212; color: #ffffff;"></div>
@endsection

@section('scripts')
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <script>
        const treeData = {!! $treeData !!};

        const width = document.getElementById('tree').offsetWidth;
        const height = document.getElementById('tree').offsetHeight;

        const svg = d3.select("#tree").append("svg")
            .attr("width", width)
            .attr("height", height)
            .style("background-color", "#121212")
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
            .attr('stroke', '#ffffff')
            .attr('stroke-width', 1);

        const nodes = svg.selectAll('.node')
            .data(root.descendants())
            .enter()
            .append('g')
            .attr('class', 'node')
            .attr('transform', d => `translate(${d.y}, ${d.x})`);

        nodes.append('circle')
            .attr('r', 8)
            .attr('fill', d => d.children ? '#2f5d77' : '#4caf50');

        nodes.append('text')
            .attr('dy', 3)
            .attr('x', d => d.children ? -12 : 12)
            .style('fill', '#ffffff')
            .text(d => d.data.name);

        // Create zoom behavior
        svg.call(d3.zoom().on("zoom", function(event) {
            svg.attr("transform", event.transform);
        }));
    </script>
@endsection
