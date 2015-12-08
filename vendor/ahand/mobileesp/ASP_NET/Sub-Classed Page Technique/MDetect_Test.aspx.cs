using System;
using System.Collections.Generic;
using System.Linq;
using System.Web;
using System.Web.UI;
using System.Web.UI.WebControls;

public partial class MDetect_Test : MDetectPage
{
    protected void Page_Load(object sender, EventArgs e)
    {
        this.FireEvents();
    }

    protected override void OnInit(EventArgs e)
    {
        base.OnInit(e);

        //As a test, let's listen for any WebKit-based device. 
        OnDetectWebkit += new DetectWebkitHandler(MDetect_Test_OnDetectWebkit);
    }

    void MDetect_Test_OnDetectWebkit(object page, MDetectPage.MDetectArgs args)
    {
        //Write the Type value from the argument to the web page. 
        Response.Write("<b>This browser is " + args.Type + "</b>");
    }

}
