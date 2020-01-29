function myPopUp( url, breite, hoehe ) {
    // mittig setzen
    var leftPos = ( screen.width ) ? ( screen.width - breite ) / 2 : 0;
    var topPos = ( screen.height ) ? ( screen.height - hoehe ) / 2 : 0;

    // eigenschaften
    var propertys = "width=" + breite + ", height=" + hoehe + ", left=" + leftPos + ",
    top=" + topPos + " toolbar=0, personalbar=0, menubar=0, scrollbars=0, resizable=0, status=0 ";

    // das popup ausführen
    var myWin = window.open( url, "myPopUp", propertys );

    // und es in den vordergrund holen
    if ( myWin ) {
        myWin.focus();
    }
}