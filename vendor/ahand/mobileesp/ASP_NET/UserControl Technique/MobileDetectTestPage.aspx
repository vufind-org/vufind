<%@ Page Language="C#" AutoEventWireup="true" CodeBehind="MobileDetectTestPage.aspx.cs" Inherits="WebApplication1.MobileDetect.MobileDetectTestPage" %>
<%@ Register  TagPrefix="mdc" TagName="MobileDetect" Src="MobileDetectControl.ascx" %>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" >
<head runat="server">
    <title></title>
</head>
<body>
    <form id="form1" runat="server">
    <div>
    <mdc:MobileDetect id="MDC" runat="server"></mdc:MobileDetect>
    </div>
    </form>
</body>
</html>
