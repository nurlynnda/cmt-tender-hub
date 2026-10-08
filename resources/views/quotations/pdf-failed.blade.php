<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>PDF not available</title></head>
<body style="font-family: sans-serif; padding: 2rem">
    <h1 style="font-size: 1.1rem">The PDF could not be made</h1>
    <p>Something went wrong while building the PDF for {{ $quotation->number }}. Please try again in a minute; if it keeps happening, tell your administrator.</p>
    <p><a href="{{ route('quotations.show', $quotation) }}">Back to the quotation</a></p>
</body>
</html>
