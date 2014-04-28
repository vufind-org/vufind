import unittest
from selenium import webdriver

class Selenium2OnSauce(unittest.TestCase):

    def setUp(self):
        desired_capabilities = {}
        desired_capabilities['name'] = 'Blueprint Javascript Test'
        desired_capabilities = webdriver.DesiredCapabilities.FIREFOX
        desired_capabilities['platform'] = "Windows 7"
        desired_capabilities['version'] = "27"
        desired_capabilities = webdriver.DesiredCapabilities.INTERNETEXPLORER
        desired_capabilities['platform'] = "Windows 7"
        desired_capabilities['version'] = "8"

        self.wd = webdriver.Remote(
            desired_capabilities=desired_capabilities,
            command_executor="http://challberg:89b4526e-51e8-40b7-9ce2-bbbe1a05f92e@ondemand.saucelabs.com:80/wd/hub"
        )
        self.wd.implicitly_wait(10)

    def tearDown(self):
        print("Link to your job: https://saucelabs.com/jobs/%s" % self.wd.session_id)
        self.wd.quit()

    def test_sauce(self):
        success = True
        wd = self.wd
        wd.get("http://localhost/vufind/?ui=bl")
        wd.find_element_by_link_text("Advanced").click()
        wd.find_element_by_id("search_lookfor0_0").click()
        wd.find_element_by_id("search_lookfor0_0").clear()
        wd.find_element_by_id("search_lookfor0_0").send_keys("bride")
        wd.find_element_by_id("search_lookfor0_1").click()
        wd.find_element_by_id("search_lookfor0_1").clear()
        wd.find_element_by_id("search_lookfor0_1").send_keys("tomb")
        wd.find_element_by_id("add_search_link_0").click()
        if not (len(wd.find_elements_by_id("search_lookfor0_3")) != 0):
            success = False
            print("verifyElementPresent failed")
        if not wd.find_element_by_xpath("//div[@id='group0SearchHolder']/div[2]/div[3]/select//option[2]").is_selected():
            wd.find_element_by_xpath("//div[@id='group0SearchHolder']/div[2]/div[3]/select//option[2]").click()
        wd.find_element_by_id("search_lookfor0_2").click()
        wd.find_element_by_id("search_lookfor0_2").clear()
        wd.find_element_by_id("search_lookfor0_2").send_keys("1883")
        if not wd.find_element_by_xpath("//div[@id='group0SearchHolder']/div[3]/div[3]/select//option[9]").is_selected():
            wd.find_element_by_xpath("//div[@id='group0SearchHolder']/div[3]/div[3]/select//option[9]").click()
        wd.find_element_by_id("addGroupLink").click()
        if not (len(wd.find_elements_by_id("group1")) != 0):
            success = False
            print("verifyElementPresent failed")
        wd.find_element_by_name("submit").click()
        if not ("(All Fields:bride AND Title:tomb AND Year of Publication:1883)" in wd.find_element_by_tag_name("html").text):
            success = False
            print("verifyTextPresent failed")
        wd.find_element_by_link_text("Edit this Advanced Search").click()
        if wd.find_element_by_id("search_lookfor0_0").get_attribute("value") != "bride":
            success = False
            print("verifyElementValue failed")
        if wd.find_element_by_id("search_lookfor0_1").get_attribute("value") != "tomb":
            success = False
            print("verifyElementValue failed")
        if wd.find_element_by_id("search_lookfor0_2").get_attribute("value") != "1883":
            success = False
            print("verifyElementValue failed")
        if wd.find_element_by_id("search_type0_1").get_attribute("value") != "Title":
            success = False
            print("verifyElementValue failed")
        if wd.find_element_by_id("search_type0_2").get_attribute("value") != "year":
            success = False
            print("verifyElementValue failed")
        wd.find_element_by_id("logo").click()
        wd.find_element_by_name("submit").click()
        wd.find_element_by_link_text("Villanova University").click()
        if not ("Institution: Villanova University" in wd.find_element_by_tag_name("html").text):
            success = False
            print("verifyTextPresent failed")
        wd.find_element_by_css_selector("img[alt=\"Delete\"]").click()
        wd.find_element_by_link_text("Add to Favorites").click()
        wd.find_element_by_id("login_username").click()
        wd.find_element_by_id("login_username").clear()
        wd.find_element_by_id("login_username").send_keys("challberg")
        wd.find_element_by_id("login_password").click()
        wd.find_element_by_id("login_password").clear()
        wd.find_element_by_id("login_password").send_keys("super wrong")
        wd.find_element_by_name("processLogin").click()
        if not ("Invalid login -- please try again." in wd.find_element_by_tag_name("html").text):
            success = False
            print("verifyTextPresent failed")
        wd.find_element_by_id("login_password").click()
        wd.find_element_by_id("login_password").clear()
        wd.find_element_by_id("login_password").send_keys("apple")
        wd.find_element_by_name("processLogin").click()
        wd.find_element_by_link_text("close").click()
        wd.find_element_by_link_text("Your Account").click()
        wd.find_element_by_link_text("Create a List").click()
        wd.find_element_by_id("list_title").click()
        wd.find_element_by_id("list_title").clear()
        wd.find_element_by_id("list_title").send_keys("new selenium lisst of diversion")
        wd.find_element_by_css_selector("form[name=\"newList\"] > input[name=\"submit\"]").click()
        wd.find_element_by_name("submit").click()
        wd.find_element_by_link_text("Add to Favorites").click()
        wd.find_element_by_link_text("or create a new list").click()
        wd.find_element_by_id("list_title").click()
        wd.find_element_by_id("list_title").clear()
        wd.find_element_by_id("list_title").send_keys("new selenium lisst of DESTINY")
        wd.find_element_by_id("list_public_1").click()
        wd.find_element_by_css_selector("form[name=\"newList\"] > input[name=\"submit\"]").click()
        wd.find_element_by_id("add_mytags").click()
        wd.find_element_by_id("add_mytags").clear()
        wd.find_element_by_id("add_mytags").send_keys("single save")
        wd.find_element_by_css_selector("form[name=\"saveRecord\"] > input.button").click()
        if not ("new selenium lisst of DESTINY" in wd.find_element_by_tag_name("html").text):
            success = False
            print("verifyTextPresent failed")
        wd.find_element_by_css_selector("label.floatleft").click()
        wd.find_element_by_id("updateCart").click()
        if wd.find_element_by_id("viewCart").text != "20 items":
            success = False
            print("verifyText failed")
        wd.find_element_by_id("viewCart").click()
        wd.find_element_by_css_selector("form[name=\"cartForm\"] > div.bulkActionButtons > label.floatleft").click()
        if not wd.find_element_by_id("cartCheckboxSelectAll").is_selected():
            wd.find_element_by_id("cartCheckboxSelectAll").click()
        wd.find_element_by_name("saveCart").click()
        wd.find_element_by_id("add_mytags").click()
        wd.find_element_by_id("add_mytags").clear()
        wd.find_element_by_id("add_mytags").send_keys("bulk save")
        wd.find_element_by_css_selector("form[name=\"bulkSave\"] > input[name=\"submit\"]").click()
        wd.find_element_by_xpath("//div[@id='record1338504']//a[.='new selenium lisst of DESTINY']").click()
        if not ("bulk (20)" in wd.find_element_by_tag_name("html").text):
            success = False
            print("verifyTextPresent failed")
        if not ("save (20)" in wd.find_element_by_tag_name("html").text):
            success = False
            print("verifyTextPresent failed")
        if not ("single (1)" in wd.find_element_by_tag_name("html").text):
            success = False
            print("verifyTextPresent failed")
        wd.find_element_by_id("viewCart").click()
        wd.find_element_by_css_selector("form[name=\"cartForm\"] > div.bulkActionButtons > label.floatleft").click()
        if not wd.find_element_by_id("cartCheckboxSelectAll").is_selected():
            wd.find_element_by_id("cartCheckboxSelectAll").click()
        wd.find_element_by_css_selector("form[name=\"cartForm\"] > div.bulkActionButtons > input[name=\"email\"]").click()
        wd.find_element_by_id("email_to").click()
        wd.find_element_by_id("email_to").clear()
        wd.find_element_by_id("email_to").send_keys("challber@villanova.edu")
        wd.find_element_by_css_selector("form[name=\"bulkEmail\"] > input[name=\"submit\"]").click()
        if not ("Message Sent" in wd.find_element_by_tag_name("html").text):
            success = False
            print("verifyTextPresent failed")
        wd.find_element_by_id("viewCart").click()
        wd.find_element_by_css_selector("form[name=\"cartForm\"] > div.bulkActionButtons > label.floatleft").click()
        if not wd.find_element_by_id("cartCheckboxSelectAll").is_selected():
            wd.find_element_by_id("cartCheckboxSelectAll").click()
        wd.find_element_by_css_selector("form[name=\"cartForm\"] > div.bulkActionButtons > input[name=\"export\"]").click()
        wd.find_element_by_css_selector("form[name=\"exportForm\"] > input[name=\"submit\"]").click()
        wd.find_element_by_link_text("close").click()
        if not ("Download File" in wd.find_element_by_tag_name("html").text):
            success = False
            print("verifyTextPresent failed")
        wd.find_element_by_link_text("close").click()
        wd.find_element_by_id("viewCart").click()
        if not wd.find_element_by_id("cartCheckboxSelectAll").is_selected():
            wd.find_element_by_id("cartCheckboxSelectAll").click()
        wd.find_element_by_css_selector("form[name=\"cartForm\"] > div.bulkActionButtons > input[name=\"export\"]").click()
        if not wd.find_element_by_xpath("//div[@id='modalDialog']/form/select//option[2]").is_selected():
            wd.find_element_by_xpath("//div[@id='modalDialog']/form/select//option[2]").click()
        wd.find_element_by_css_selector("form[name=\"exportForm\"] > input[name=\"submit\"]").click()
        if not ("Start export to EndNoteWeb" in wd.find_element_by_tag_name("html").text):
            success = False
            print("verifyTextPresent failed")
        wd.find_element_by_link_text("close").click()
        wd.find_element_by_id("viewCart").click()
        wd.find_element_by_css_selector("form[name=\"cartForm\"] > div.bulkActionButtons > label.floatleft").click()
        if not wd.find_element_by_id("cartCheckboxSelectAll").is_selected():
            wd.find_element_by_id("cartCheckboxSelectAll").click()
        wd.find_element_by_css_selector("form[name=\"cartForm\"] > div.bulkActionButtons > input[name=\"export\"]").click()
        if not wd.find_element_by_xpath("//div[@id='modalDialog']/form/select//option[3]").is_selected():
            wd.find_element_by_xpath("//div[@id='modalDialog']/form/select//option[3]").click()
        wd.find_element_by_css_selector("form[name=\"exportForm\"] > input[name=\"submit\"]").click()
        if not ("Start export to RefWorks" in wd.find_element_by_tag_name("html").text):
            success = False
            print("verifyTextPresent failed")
        wd.find_element_by_link_text("close").click()
        wd.find_element_by_id("viewCart").click()
        if not wd.find_element_by_id("cartcheckbox_0").is_selected():
            wd.find_element_by_id("cartcheckbox_0").click()
        wd.find_element_by_css_selector("form[name=\"cartForm\"] > div.bulkActionButtons > input[name=\"delete\"]").click()
        wd.find_element_by_link_text("close").click()
        if not ("19 items" in wd.find_element_by_tag_name("html").text):
            success = False
            print("verifyTextPresent failed")
        wd.find_element_by_id("viewCart").click()
        wd.find_element_by_css_selector("form[name=\"cartForm\"] > div.bulkActionButtons > label.floatleft").click()
        wd.find_element_by_id("cartCheckboxSelectAll").click()
        if not wd.find_element_by_id("cartcheckbox_0").is_selected():
            success = False
            print("verifyElementSelected failed")
        wd.find_element_by_css_selector("form[name=\"cartForm\"] > div.bulkActionButtons > label.floatleft").click()
        if wd.find_element_by_id("cartCheckboxSelectAll").is_selected():
            wd.find_element_by_id("cartCheckboxSelectAll").click()
        wd.find_element_by_name("empty").click()
        wd.switch_to_alert().accept()
        wd.find_element_by_link_text("close").click()
        wd.find_element_by_link_text("Your Account").click()
        wd.find_element_by_link_text("new selenium lisst of DESTINY").click()
        wd.find_element_by_link_text("Delete List").click()
        wd.find_element_by_name("confirm").click()
        wd.find_element_by_link_text("new selenium lisst of diversion").click()
        wd.find_element_by_link_text("Delete List").click()
        wd.find_element_by_name("confirm").click()
        wd.find_element_by_id("logo").click()
        self.assertTrue(success)

if __name__ == "__main__":
    unittest.main()
