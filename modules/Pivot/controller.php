<?php
/**
 *
 *
 * @package
 * @copyright SalesAgility Ltd http://www.salesagility.com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU AFFERO GENERAL PUBLIC LICENSE
 * along with this program; if not, see http://www.gnu.org/licenses
 * or write to the Free Software Foundation,Inc., 51 Franklin Street,
 * Fifth Floor, Boston, MA 02110-1301  USA
 *
 * @author Salesagility Ltd <support@salesagility.com>
 */
if(!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class PivotController extends SugarController {
    protected $nullSqlPlaceholder = 'undefined';
    protected $action_remap = array('index'=>'pivotdata');

    function action_pivotdata() {
        $this->view = 'pivotdata';
    }

    public function action_savePivot()
    {
        $config = htmlspecialchars_decode($_REQUEST['config']);

        $type = $_REQUEST['type'];
        $name = $_REQUEST['name'];

        $pivotBean = BeanFactory::getBean('Pivot');
        $pivotBean->name = $name;
        $pivotBean->type = $type;
        $pivotBean->config = $config;
        $pivotBean->save();
    }

    public function action_deletePivot()
    {
        $id = $_REQUEST['id'];
        $pivotBean = BeanFactory::getBean('Pivot',$id);
        $pivotBean->deleted = true;
        $pivotBean->save();
    }

    public function action_getSavedPivotList()
    {
        $pivotBean = BeanFactory::getBean('Pivot');
        $beanList = $pivotBean->get_full_list('name');
        $returnArray = [];
        if(!is_null($beanList))
        {
            foreach ($beanList as $b) {
                $bean = new stdClass();
                $bean->type = $b->type;
                $bean->config = htmlspecialchars_decode($b->config);
                $bean->name = $b->name;
                $bean->id = $b->id;
                $returnArray[] = $bean;
            }
        }

        echo json_encode($returnArray);
    }

    function build_report_access_query(SugarBean $module, $alias){

        $module->table_name = $alias;
        $where = '';
        if($module->bean_implements('ACL') && ACLController::requireOwner($module->module_dir, 'list') )
        {
            global $current_user;
            $owner_where = $module->getOwnerWhere($current_user->id);
            $where = ' AND '.$owner_where;

        }

        if(file_exists('modules/SecurityGroups/SecurityGroup.php')){
            /* BEGIN - SECURITY GROUPS */
            if($module->bean_implements('ACL') && ACLController::requireSecurityGroup($module->module_dir, 'list') )
            {
                require_once('modules/SecurityGroups/SecurityGroup.php');
                global $current_user;
                $owner_where = $module->getOwnerWhere($current_user->id);
                $group_where = SecurityGroup::getGroupWhere($alias,$module->module_dir,$current_user->id);
                if(!empty($owner_where)){
                    $where .= " AND (".  $owner_where." or ".$group_where.") ";
                } else {
                    $where .= ' AND '.  $group_where;
                }
            }
        }

        return $where;
    }


    public function action_getAccountsPivotData()
    {
        global $mod_strings;
        $returnArray = [];
        $db = DBManagerFactory::getInstance();

        $query = <<<EOF
        SELECT
            COALESCE(name,'$this->nullSqlPlaceholder') as accountName,
            COALESCE(account_type,'$this->nullSqlPlaceholder') as account_type,
            COALESCE(industry,'$this->nullSqlPlaceholder') as industry,
            COALESCE(billing_address_country,'$this->nullSqlPlaceholder') as billing_address_country
        FROM accounts
        WHERE accounts.deleted = false
EOF;

        $accounts = BeanFactory::getBean('Accounts');
        $aclWhere = $this->build_report_access_query($accounts,$accounts->table_name);

        $queryString = $query.$aclWhere;

        $result = $db->query($queryString);

        while ($row = $db->fetchByAssoc($result)) {
            $x = new stdClass();
            $x->$mod_strings['LBL_AN_ACCOUNTS_ACCOUNT_NAME'] = $row['accountName'];
            $x->$mod_strings['LBL_AN_ACCOUNTS_ACCOUNT_TYPE'] = $row['account_type'];
            $x->$mod_strings['LBL_AN_ACCOUNTS_ACCOUNT_INDUSTRY'] = $row['industry'];
            $x->$mod_strings['LBL_AN_ACCOUNTS_ACCOUNT_BILLING_COUNTRY'] = $row['billing_address_country'];
            $returnArray[] = $x;
        }
        echo json_encode($returnArray);
    }

    public function action_getLeadsPivotData()
    {
        global $mod_strings;
        $returnArray = [];
        $db = DBManagerFactory::getInstance();

        $query = <<<EOF
        SELECT
            RTRIM(LTRIM(CONCAT(COALESCE(users.first_name,''),' ',COALESCE(users.last_name,'')))) as assignedUser,
            leads.status,
            COALESCE(lead_source, '$this->nullSqlPlaceholder') as leadSource,
			COALESCE(campaigns.name, '$this->nullSqlPlaceholder') as campaignName,
			CAST(YEAR(leads.date_entered) as CHAR(10)) as year,
            COALESCE(QUARTER(leads.date_entered),'$this->nullSqlPlaceholder') as quarter,
			concat('(',MONTH(leads.date_entered),') ',MONTHNAME(leads.date_entered)) as month,
			CAST(WEEK(leads.date_entered) as CHAR(5)) as week,
			DAYNAME(leads.date_entered) as day
        FROM leads
        INNER JOIN users
            ON leads.assigned_user_id = users.id
		LEFT JOIN campaigns
			ON leads.campaign_id = campaigns.id
			AND campaigns.deleted = false
        WHERE leads.deleted = false
        AND users.deleted = false

EOF;

        $leads = BeanFactory::getBean('Leads');
        $aclWhereLeads = $this->build_report_access_query($leads,$leads->table_name);

        $queryString = $query.$aclWhereLeads;
        $result = $db->query($queryString);

        while ($row = $db->fetchByAssoc($result)) {
            $x = new stdClass();
            $x->$mod_strings['LBL_AN_LEADS_ASSIGNED_USER'] = $row['assignedUser'];
            $x->$mod_strings['LBL_AN_LEADS_STATUS'] = $row['status'];
            $x->$mod_strings['LBL_AN_LEADS_LEAD_SOURCE'] = $row['leadSource'];
            $x->$mod_strings['LBL_AN_LEADS_CAMPAIGN_NAME'] = $row['campaignName'];
            $x->$mod_strings['LBL_AN_LEADS_YEAR'] = $row['year'];
            $x->$mod_strings['LBL_AN_LEADS_QUARTER'] = $row['quarter'];
            $x->$mod_strings['LBL_AN_LEADS_MONTH'] = $row['month'];
            $x->$mod_strings['LBL_AN_LEADS_WEEK'] = $row['week'];
            $x->$mod_strings['LBL_AN_LEADS_DAY'] = $row['day'];

            $returnArray[] = $x;
        }
        echo json_encode($returnArray);
    }


    public function action_getSalesPivotData()
    {
        global $mod_strings;
        $returnArray = [];
        $db = DBManagerFactory::getInstance();

        $query = <<<EOF
        SELECT
			accounts.name as accountName,
            opportunities.name as opportunityName,
            RTRIM(LTRIM(CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')))) as assignedUser,
            COALESCE(opportunity_type,'$this->nullSqlPlaceholder') as opportunity_type,
            lead_source,
            amount,
            sales_stage,
            probability,
            date_closed as expectedCloseDate,
			COALESCE(QUARTER(date_closed),'$this->nullSqlPlaceholder') as salesQuarter,
			concat('(',MONTH(date_closed),') ',MONTHNAME(date_closed)) as salesMonth,
			CAST(WEEK(date_closed) as CHAR(5)) as salesWeek,
			DAYNAME(date_closed) as salesDay,
			CAST(YEAR(date_closed) as CHAR(10)) as salesYear,
            COALESCE(campaigns.name,'$this->nullSqlPlaceholder') as campaign
        FROM opportunities
		INNER JOIN accounts_opportunities
			ON accounts_opportunities.opportunity_id = opportunities.id
		INNER JOIN accounts
			ON accounts_opportunities.account_id = accounts.id
        INNER JOIN users
            ON opportunities.assigned_user_id = users.id
        LEFT JOIN campaigns
            ON opportunities.campaign_id = campaigns.id
            AND campaigns.deleted = false
        WHERE opportunities.deleted = false
        AND accounts_opportunities.deleted = false
        AND accounts.deleted = false
        AND users.deleted = false

EOF;

        $opps = BeanFactory::getBean('Opportunities');
        $aclWhereOpps = $this->build_report_access_query($opps,$opps->table_name);

        $queryString = $query.$aclWhereOpps;
        $result = $db->query($queryString);

        while ($row = $db->fetchByAssoc($result)) {
            $x = new stdClass();
            $x->$mod_strings['LBL_AN_SALES_ACCOUNT_NAME'] = $row['accountName'];
            $x->$mod_strings['LBL_AN_SALES_OPPORTUNITY_NAME'] = $row['opportunityName'];
            $x->$mod_strings['LBL_AN_SALES_ASSIGNED_USER'] = $row['assignedUser'];
            $x->$mod_strings['LBL_AN_SALES_OPPORTUNITY_TYPE'] = $row['opportunity_type'];
            $x->$mod_strings['LBL_AN_SALES_LEAD_SOURCE'] = $row['lead_source'];
            $x->$mod_strings['LBL_AN_SALES_AMOUNT'] = $row['amount'];
            $x->$mod_strings['LBL_AN_SALES_STAGE'] = $row['sales_stage'];
            $x->$mod_strings['LBL_AN_SALES_PROBABILITY'] = $row['probability'];
            $x->$mod_strings['LBL_AN_SALES_DATE'] = $row['date_closed'];

            $x->$mod_strings['LBL_AN_SALES_QUARTER'] = $row['salesQuarter'];
            $x->$mod_strings['LBL_AN_SALES_MONTH'] = $row['salesMonth'];
            $x->$mod_strings['LBL_AN_SALES_WEEK'] = $row['salesWeek'];
            $x->$mod_strings['LBL_AN_SALES_DAY'] = $row['salesDay'];
            $x->$mod_strings['LBL_AN_SALES_YEAR'] = $row['salesYear'];
            $x->$mod_strings['LBL_AN_SALES_CAMPAIGN'] = $row['campaign'];



            $returnArray[] = $x;
        }
        echo json_encode($returnArray);
    }

    public function action_getServicePivotData()
    {
        global $mod_strings;
        $returnArray = [];
        $db = DBManagerFactory::getInstance();

        $query = <<<EOF
        SELECT
            accounts.name,
            cases.state,
            cases.status,
            cases.priority,
            DAYNAME(cases.date_entered) as day,
            CAST(WEEK(cases.date_entered) as CHAR(5)) as week,
            concat('(',MONTH(cases.date_entered),') ',MONTHNAME(cases.date_entered)) as month,
            COALESCE(QUARTER(cases.date_entered),'$this->nullSqlPlaceholder') as quarter,
            CAST(YEAR(cases.date_entered) as CHAR(10)) as year,
            COALESCE(NULLIF(RTRIM(LTRIM(CONCAT(COALESCE(u2.first_name,''),' ',COALESCE(u2.last_name,'')))),''),'$this->nullSqlPlaceholder') as contactName,
            RTRIM(LTRIM(CONCAT(COALESCE(users.first_name,''),' ',COALESCE(users.last_name,'')))) as assignedUser
        FROM cases
        INNER JOIN users
            ON cases.assigned_user_id = users.id
        INNER JOIN accounts
            ON cases.account_id = accounts.id
        LEFT JOIN users u2
            ON cases.contact_created_by_id = u2.id
            AND u2.deleted = false
        WHERE cases.deleted = false
        AND users.deleted = false
        AND accounts.deleted = false

EOF;

        $opps = BeanFactory::getBean('Opportunities');
        $aclWhereOpps = $this->build_report_access_query($opps,$opps->table_name);

        $queryString = $query.$aclWhereOpps;
        $result = $db->query($queryString);

        while ($row = $db->fetchByAssoc($result)) {
            $x = new stdClass();
            $x->$mod_strings['LBL_AN_SERVICE_ACCOUNT_NAME'] = $row['name'];
            $x->$mod_strings['LBL_AN_SERVICE_STATE'] = $row['state'];
            $x->$mod_strings['LBL_AN_SERVICE_STATUS'] = $row['status'];
            $x->$mod_strings['LBL_AN_SERVICE_PRIORITY'] = $row['priority'];
            $x->$mod_strings['LBL_AN_SERVICE_CREATED_DAY'] = $row['day'];
            $x->$mod_strings['LBL_AN_SERVICE_CREATED_WEEK'] = $row['week'];
            $x->$mod_strings['LBL_AN_SERVICE_CREATED_MONTH'] = $row['month'];
            $x->$mod_strings['LBL_AN_SERVICE_CREATED_QUARTER'] = $row['quarter'];
            $x->$mod_strings['LBL_AN_SERVICE_CREATED_YEAR'] = $row['year'];
            $x->$mod_strings['LBL_AN_SERVICE_CONTACT_NAME'] = $row['contactName'];
            $x->$mod_strings['LBL_AN_SERVICE_ASSIGNED_TO'] = $row['assignedUser'];

            $returnArray[] = $x;
        }
        echo json_encode($returnArray);
    }

    public function action_getActivitiesPivotData()
    {
        global $mod_strings;
        $returnArray = [];
        $db = DBManagerFactory::getInstance();

        $query = <<<EOF
        SELECT
            'call' as type
            , calls.name
            , calls.status
            , RTRIM(LTRIM(CONCAT(COALESCE(users.first_name,''),' ',COALESCE(users.last_name,'')))) as assignedUser
        FROM calls
        LEFT JOIN users
            ON calls.assigned_user_id = users.id
        WHERE calls.deleted = false
        UNION
        SELECT
            'meeting' as type
            , meetings.name
            , meetings.status
            , RTRIM(LTRIM(CONCAT(COALESCE(users.first_name,''),' ',COALESCE(users.last_name,'')))) as assignedUser
        FROM meetings
        LEFT JOIN users
            ON meetings.assigned_user_id = users.id
        WHERE meetings.deleted = false
        UNION
        SELECT
            'task' as type
            , tasks.name
            , tasks.status
            , RTRIM(LTRIM(CONCAT(COALESCE(users.first_name,''),' ',COALESCE(users.last_name,'')))) as assignedUser
        FROM tasks
        LEFT JOIN users
            ON tasks.assigned_user_id = users.id
        WHERE tasks.deleted = false
EOF;

        $opps = BeanFactory::getBean('Opportunities');
        $aclWhereOpps = $this->build_report_access_query($opps,$opps->table_name);

        $queryString = $query.$aclWhereOpps;
        $result = $db->query($queryString);

        while ($row = $db->fetchByAssoc($result)) {
            $x = new stdClass();
            $x->$mod_strings['LBL_AN_ACTIVITIES_TYPE'] = $row['type'];
            $x->$mod_strings['LBL_AN_ACTIVITIES_NAME'] = $row['name'];
            $x->$mod_strings['LBL_AN_ACTIVITIES_STATUS'] = $row['status'];
            $x->$mod_strings['LBL_AN_ACTIVITIES_ASSIGNED_TO'] = $row['assignedUser'];

            $returnArray[] = $x;
        }
        echo json_encode($returnArray);
    }

    public function action_getActivityMeetingsPivotData()
    {
        global $mod_strings;
        $returnArray = [];
        $db = DBManagerFactory::getInstance();

        $query = <<<EOF
        SELECT
            meetings.name as meetingName,
            meetings.status as meetingStatus,
            RTRIM(LTRIM(CONCAT(COALESCE(users.first_name,''),' ',COALESCE(users.last_name,'')))) as assignedUser,
            COALESCE(accounts.name, '$this->nullSqlPlaceholder') as accountName,
            DAYNAME(meetings.date_start) as day,
            CAST(WEEK(meetings.date_start) as CHAR(5)) as week,
             concat('(',MONTH(meetings.date_start),') ',MONTHNAME(meetings.date_start)) as month,
             COALESCE(QUARTER(meetings.date_start),'$this->nullSqlPlaceholder') as quarter
             , YEAR(meetings.date_start) as year
        FROM meetings
        LEFT JOIN users
            ON meetings.assigned_user_id = users.id
        LEFT JOIN accounts
            ON meetings.parent_id = accounts.id
        WHERE meetings.deleted = false
        AND users.deleted = false
        AND accounts.deleted = false
EOF;

        $opps = BeanFactory::getBean('Opportunities');
        $aclWhereOpps = $this->build_report_access_query($opps,$opps->table_name);

        $queryString = $query.$aclWhereOpps;
        $result = $db->query($queryString);

        while ($row = $db->fetchByAssoc($result)) {
            $x = new stdClass();
            $x->$mod_strings['LBL_AN_ACTIVITY_MEETINGS_NAME'] = $row['name'];
            $x->$mod_strings['LBL_AN_ACTIVITY_MEETINGS_STATE'] = $row['state'];
            $x->$mod_strings['LBL_AN_ACTIVITY_MEETINGS_STATUS'] = $row['status'];
            $x->$mod_strings['LBL_AN_ACTIVITY_MEETINGS_PRIORITY'] = $row['priority'];
            $x->$mod_strings['LBL_AN_ACTIVITY_MEETINGS_CREATED_DAY'] = $row['day'];
            $x->$mod_strings['LBL_AN_ACTIVITY_MEETINGS_CREATED_WEEK'] = $row['week'];
            $x->$mod_strings['LBL_AN_ACTIVITY_MEETINGS_CREATED_MONTH'] = $row['month'];
            $x->$mod_strings['LBL_AN_ACTIVITY_MEETINGS_CREATED_QUARTER'] = $row['quarter'];
            $x->$mod_strings['LBL_AN_ACTIVITY_MEETINGS_CREATED_YEAR'] = $row['year'];
            $x->$mod_strings['LBL_AN_ACTIVITY_MEETINGS_CONTACT_NAME'] = $row['contactName'];
            $x->$mod_strings['LBL_AN_ACTIVITY_MEETINGS_ASSIGNED_TO'] = $row['assignedUser'];

            $returnArray[] = $x;
        }
        echo json_encode($returnArray);
    }

    public function action_getMarketingPivotData()
    {
        global $mod_strings;
        $returnArray = [];
        $db = DBManagerFactory::getInstance();

        $query = <<<EOF
        SELECT
              COALESCE(campaigns.status,'$this->nullSqlPlaceholder') as campaignStatus
            , COALESCE(campaigns.campaign_type,'$this->nullSqlPlaceholder') as campaignType
            , COALESCE(campaigns.budget,'$this->nullSqlPlaceholder') as campaignBudget
            , COALESCE(campaigns.expected_cost,'$this->nullSqlPlaceholder') as campaignExpectedCost
            , COALESCE(campaigns.expected_revenue,'$this->nullSqlPlaceholder') as campaignExpectedRevenue
            , opportunities.name as opportunityName
            , opportunities.amount as opportunityAmount
            , opportunities.sales_stage as opportunitySalesStage
            , RTRIM(LTRIM(CONCAT(COALESCE(users.first_name,''),' ',COALESCE(users.last_name,'')))) as assignedUser
            , accounts.name as accountsName
        FROM opportunities
        LEFT JOIN users
            ON opportunities.assigned_user_id = users.id
        LEFT JOIN accounts_opportunities
            ON opportunities.id =  accounts_opportunities.opportunity_id
        LEFT JOIN accounts
            ON accounts_opportunities.account_id = accounts.id
        LEFT JOIN campaigns
            ON opportunities.campaign_id = campaigns.id
        WHERE opportunities.deleted = false
        AND users.deleted = false
        AND accounts_opportunities.deleted = false
        AND accounts.deleted = false
        AND campaigns.deleted = false
EOF;

        $opps = BeanFactory::getBean('Opportunities');
        $aclWhereOpps = $this->build_report_access_query($opps,$opps->table_name);

        $queryString = $query.$aclWhereOpps;
        $result = $db->query($queryString);

        while ($row = $db->fetchByAssoc($result)) {
            $x = new stdClass();
            $x->status = $row['campaignStatus'];
            $x->type = $row['campaignType'];
            $x->budget = $row['campaignBudget'];
            $x->expectedCost = $row['campaignExpectedCost'];
            $x->expectedRevenue = $row['campaignExpectedRevenue'];
            $x->opportunityName = $row['opportunityName'];
            $x->opportunityAmount = $row['opportunityAmount'];
            $x->opportunitySalesStage = $row['opportunitySalesStage'];
            $x->opportunityAssignedTo = $row['assignedUser'];
            $x->accountName = $row['accountsName'];

            $returnArray[] = $x;
        }
        echo json_encode($returnArray);
    }

    public function action_getMarketingActivityPivotData()
    {
        $returnArray = [];
        $db = DBManagerFactory::getInstance();

        $query = <<<EOF
        SELECT
            campaigns.name,
            campaign_log.activity_date,
            campaign_log.activity_type,
            campaign_log.related_type,
            campaign_log.related_id
        FROM campaigns
        LEFT JOIN campaign_log
            ON campaigns.id = campaign_log.campaign_id
        where campaigns.deleted = false
        and campaign_log.deleted = false
EOF;

        $opps = BeanFactory::getBean('Opportunities');
        $aclWhereOpps = $this->build_report_access_query($opps,$opps->table_name);

        $queryString = $query.$aclWhereOpps;
        $result = $db->query($queryString);

        while ($row = $db->fetchByAssoc($result)) {
            $x = new stdClass();
            $x->campaignName = $row['name'];
            $x->activityDate = $row['activity_date'];
            $x->activityType = $row['activity_type'];
            $x->relatedType = $row['related_type'];
            $x->relatedId = $row['related_id'];


            $returnArray[] = $x;
        }
        echo json_encode($returnArray);
    }

    public function action_getQuotesPivotData()
    {
        $returnArray = [];
        $db = DBManagerFactory::getInstance();

        $query = <<<EOF
    SELECT
	opportunities.name as opportunityName,
	opportunities.opportunity_type as opportunityType,
	opportunities.lead_source as opportunityLeadSource,
	opportunities.sales_stage as opportunitySalesStage,
	accounts.name as accountName,
	RTRIM(LTRIM(CONCAT(COALESCE(contacts.first_name,''),' ',COALESCE(contacts.last_name,'')))) as contactName,
	aos_products.name as productName,
	RTRIM(LTRIM(CONCAT(COALESCE(users.first_name,''),' ',COALESCE(users.last_name,'')))) as assignedUser,
	aos_products_quotes.product_qty as productQty,
	aos_products_quotes.product_list_price as productListPrice,
	aos_products_quotes.product_cost_price as productCostPrice,
	aos_products.price as productPrice,
	aos_quotes.discount_amount as discountAmount,
	aos_product_categories.name as categoryName,
	aos_products_quotes.product_total_price as productTotal,
	aos_quotes.total_amount as grandTotal,
	case
		when aos_products_quotes.product_id = 0 then 'Service'
		else 'Product'
	end itemType,
	aos_quotes.date_entered as dateCreated,
    DAYNAME(aos_quotes.date_entered) as dateCreatedDay,
    CAST(WEEK(aos_quotes.date_entered) as CHAR(5)) as dateCreatedWeek,
    concat('(',MONTH(aos_quotes.date_entered),') ',MONTHNAME(aos_quotes.date_entered)) as dateCreatedMonth,
    COALESCE(QUARTER(aos_quotes.date_entered),'$this->nullSqlPlaceholder') as dateCreatedQuarter,
    YEAR(aos_quotes.date_entered) as dateCreatedYear

    FROM aos_quotes
    LEFT JOIN accounts
        ON aos_quotes.billing_account_id = accounts.id
        AND accounts.deleted = false
    LEFT JOIN contacts
        ON aos_quotes.billing_contact_id = contacts.id
        AND contacts.deleted = false
    LEFT JOIN aos_products_quotes
        ON aos_quotes.id = aos_products_quotes.parent_id
        AND aos_products_quotes.deleted = false
    LEFT JOIN aos_products
        ON aos_products_quotes.product_id = aos_products.id
        AND aos_products.deleted = false
    LEFT JOIN opportunities
        ON aos_quotes.opportunity_id = opportunities.id
        AND opportunities.deleted = false
    LEFT JOIN users
        ON aos_quotes.assigned_user_id = users.id
        AND users.deleted = false
    LEFT JOIN aos_product_categories
        ON aos_products.aos_product_category_id = aos_product_categories.id
        AND aos_product_categories.deleted = false
    WHERE aos_quotes.deleted = false
EOF;

        $opps = BeanFactory::getBean('Opportunities');
        $aclWhereOpps = $this->build_report_access_query($opps,$opps->table_name);

        $queryString = $query.$aclWhereOpps;
        $result = $db->query($queryString);

        while ($row = $db->fetchByAssoc($result)) {
            $x = new stdClass();
            $x->opportunityName = $row['opportunityName'];
            $x->opportunityType = $row['opportunityType'];
            $x->opportunityLeadSource = $row['opportunityLeadSource'];
            $x->opportunitySalesStage = $row['opportunitySalesStage'];
            $x->accoutName = $row['accountName'];
            $x->contactName = $row['contactName'];
            $x->itemName = $row['productName'];
            $x->itemType = $row['itemType'];
            $x->itemCategory = $row['categoryName'];
            $x->itemQty = $row['productQty'];
            $x->itemListPrice = $row['productListPrice'];
            $x->itemSalePrice = $row['productPrice'];
            $x->itemCostPrice = $row['productCostPrice'];
            $x->itemDiscountAmount = $row['discountAmount'];
            $x->itemTotal = $row['productTotal'];
            $x->grandTotal = $row['grandTotal'];
            $x->assignedTo = $row['assignedUser'];

            $x->dateCreated = $row['dateCreated'];
            $x->dateCreatedWeek = $row['dateCreatedDay'];
            $x->dateCreatedWeek = $row['dateCreatedWeek'];
            $x->dateCreatedMonth = $row['dateCreatedMonth'];
            $x->dateCreatedQuarter = $row['dateCreatedQuarter'];
            $x->dateCreatedYear = $row['dateCreatedYear'];


            $returnArray[] = $x;
        }
        echo json_encode($returnArray);
    }
}