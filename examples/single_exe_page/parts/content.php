<?php
function page_title(): string {
    return 'JX bundled PHP page';
}

function page_body(): string {
    return 'This page uses require_once, CSS, and SVG image assets bundled by jx into the generated executable.';
}
