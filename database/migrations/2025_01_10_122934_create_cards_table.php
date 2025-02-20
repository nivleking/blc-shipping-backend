<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Type -> Kalau id nya kelipatan 5, maka type nya adalah 'Reefer', selain itu 'Dry'
// Priority -> Committed dan Non-Committed
// Origin -> TBA
// Destination -> TBA
// Quantity -> Jumlah kontainer dalam 1 sales call card
// Revenue -> Total pendapatan dari semua kontainer dalam 1 sales call card

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('priority');
            $table->string('origin');
            $table->string('destination');
            $table->integer('quantity');
            $table->decimal('revenue', 45, 35);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};

// In database: 1.42423259595954595959999955
// In jasper report excel: 1,4242325959596

// In database: 1.42423259595954595959999955
// In jasper report excel: 1,42423259595955

// In database: 0.42423259595954595959999955
// In jasper report excel: 0,424232595959546

// In database: 0.42423259595954585756543255
// In jasper report excel: 0,424232595959546

// In database:
// 0.42423259595954545756543255
// jasper
// 0.42423259595954545756543255

// In database:
// 0.42423259595954545756543255969612345
// jasper
// 0.424232595959545457565433 (24) -> bigdecimal
// 0.42423259595954545756543255969612345 (35) -> bigdecimal
// 0.424232595959545457565432559696 (30)
// 0.42423259595954545756543256 (26)
// 0.42423259595954545756543255969612345 (35)

// <?xml version="1.0" encoding="UTF-8"?>
// <!-- Created with Jaspersoft Studio version 6.21.2.final using JasperReports Library version 6.21.2-8434a0bd7c3bbc37cbf916f2968d35e4b165821a  -->
// <jasperReport xmlns="http://jasperreports.sourceforge.net/jasperreports" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://jasperreports.sourceforge.net/jasperreports http://jasperreports.sourceforge.net/xsd/jasperreport.xsd" name="TESTING" pageWidth="800" pageHeight="842" columnWidth="760" leftMargin="20" rightMargin="20" topMargin="20" bottomMargin="20" uuid="c688428b-8a87-407e-b8f1-265635f6d066">
// 	<property name="com.jaspersoft.studio.data.defaultdataadapter" value="New Data Adapter1"/>
// 	<property name="com.jaspersoft.studio.data.sql.tables" value="Y2FyZHMgLDE1LDE1LGI2MGU3MTE1LWYxYTYtNDQwMC1iYmY2LTBmNGNmNDZkNDE4Zjs="/>
// 	<property name="net.sf.jasperreports.export.xls.exclude.origin.keep.first.band.1" value="pageHeader"/>
// 	<property name="net.sf.jasperreports.export.xls.exclude.origin.band.2" value="pageFooter"/>
// 	<property name="net.sf.jasperreports.export.xls.exclude.origin.band.3" value="lastPageFooter"/>
// 	<property name="net.sf.jasperreports.export.xls.remove.empty.space.between.rows" value="true"/>
// 	<property name="net.sf.jasperreports.export.xls.remove.empty.space.between.columns" value="true"/>
// 	<property name="net.sf.jasperreports.export.xls.white.page.background" value="false"/>
// 	<property name="net.sf.jasperreports.export.xls.detect.cell.type" value="false"/>
//     <property name="net.sf.jasperreports.export.xls.force.string.type" value="true"/>
//     <property name="net.sf.jasperreports.export.xls.cell.locked" value="true"/>
// 	<property name="net.sf.jasperreports.export.xls.ignore.graphics" value="false"/>
// 	<property name="net.sf.jasperreports.export.xls.ignore.cell.border" value="true"/>
// 	<style name="Table_TH" mode="Opaque" backcolor="#F0F8FF">
// 		<box>
// 			<pen lineWidth="0.5" lineColor="#000000"/>
// 			<topPen lineWidth="0.5" lineColor="#000000"/>
// 			<leftPen lineWidth="0.5" lineColor="#000000"/>
// 			<bottomPen lineWidth="0.5" lineColor="#000000"/>
// 			<rightPen lineWidth="0.5" lineColor="#000000"/>
// 		</box>
// 	</style>
// 	<style name="Table_CH" mode="Opaque" backcolor="#BFE1FF">
// 		<box>
// 			<pen lineWidth="0.5" lineColor="#000000"/>
// 			<topPen lineWidth="0.5" lineColor="#000000"/>
// 			<leftPen lineWidth="0.5" lineColor="#000000"/>
// 			<bottomPen lineWidth="0.5" lineColor="#000000"/>
// 			<rightPen lineWidth="0.5" lineColor="#000000"/>
// 		</box>
// 	</style>
// 	<style name="Table_TD" mode="Opaque" backcolor="#FFFFFF">
// 		<box>
// 			<pen lineWidth="0.5" lineColor="#000000"/>
// 			<topPen lineWidth="0.5" lineColor="#000000"/>
// 			<leftPen lineWidth="0.5" lineColor="#000000"/>
// 			<bottomPen lineWidth="0.5" lineColor="#000000"/>
// 			<rightPen lineWidth="0.5" lineColor="#000000"/>
// 		</box>
// 	</style>
// 	<subDataset name="Dataset1" uuid="b3fa58de-e3e1-4bdd-a4f5-f7f80c472f0d">
// 		<property name="com.jaspersoft.studio.data.sql.tables" value=""/>
// 		<property name="com.jaspersoft.studio.data.defaultdataadapter" value="New Data Adapter1"/>
// 		<queryString language="SQL">
// 			<![CDATA[SELECT *
// FROM cards
// order by revenue asc]]>
// 		</queryString>
// 		<field name="id" class="java.lang.Long">
// 			<property name="com.jaspersoft.studio.field.name" value="id"/>
// 			<property name="com.jaspersoft.studio.field.label" value="id"/>
// 			<property name="com.jaspersoft.studio.field.tree.path" value="cards"/>
// 		</field>
// 		<field name="type" class="java.lang.String">
// 			<property name="com.jaspersoft.studio.field.name" value="type"/>
// 			<property name="com.jaspersoft.studio.field.label" value="type"/>
// 			<property name="com.jaspersoft.studio.field.tree.path" value="cards"/>
// 		</field>
// 		<field name="priority" class="java.lang.String">
// 			<property name="com.jaspersoft.studio.field.name" value="priority"/>
// 			<property name="com.jaspersoft.studio.field.label" value="priority"/>
// 			<property name="com.jaspersoft.studio.field.tree.path" value="cards"/>
// 		</field>
// 		<field name="origin" class="java.lang.String">
// 			<property name="com.jaspersoft.studio.field.name" value="origin"/>
// 			<property name="com.jaspersoft.studio.field.label" value="origin"/>
// 			<property name="com.jaspersoft.studio.field.tree.path" value="cards"/>
// 		</field>
// 		<field name="destination" class="java.lang.String">
// 			<property name="com.jaspersoft.studio.field.name" value="destination"/>
// 			<property name="com.jaspersoft.studio.field.label" value="destination"/>
// 			<property name="com.jaspersoft.studio.field.tree.path" value="cards"/>
// 		</field>
// 		<field name="quantity" class="java.lang.Integer">
// 			<property name="com.jaspersoft.studio.field.name" value="quantity"/>
// 			<property name="com.jaspersoft.studio.field.label" value="quantity"/>
// 			<property name="com.jaspersoft.studio.field.tree.path" value="cards"/>
// 		</field>
// 		<field name="revenue" class="java.lang.String">
// 			<property name="com.jaspersoft.studio.field.name" value="revenue"/>
// 			<property name="com.jaspersoft.studio.field.label" value="revenue"/>
// 			<property name="com.jaspersoft.studio.field.tree.path" value="cards"/>
// 		</field>
// 		<field name="created_at" class="java.sql.Timestamp">
// 			<property name="com.jaspersoft.studio.field.name" value="created_at"/>
// 			<property name="com.jaspersoft.studio.field.label" value="created_at"/>
// 			<property name="com.jaspersoft.studio.field.tree.path" value="cards"/>
// 		</field>
// 		<field name="updated_at" class="java.sql.Timestamp">
// 			<property name="com.jaspersoft.studio.field.name" value="updated_at"/>
// 			<property name="com.jaspersoft.studio.field.label" value="updated_at"/>
// 			<property name="com.jaspersoft.studio.field.tree.path" value="cards"/>
// 		</field>
// 	</subDataset>
// 	<queryString>
// 		<![CDATA[SELECT *
// FROM cards]]>
// 	</queryString>
// 	<field name="id" class="java.lang.Long">
// 		<property name="com.jaspersoft.studio.field.name" value="id"/>
// 		<property name="com.jaspersoft.studio.field.label" value="id"/>
// 		<property name="com.jaspersoft.studio.field.tree.path" value="cards"/>
// 	</field>
// 	<field name="type" class="java.lang.String">
// 		<property name="com.jaspersoft.studio.field.name" value="type"/>
// 		<property name="com.jaspersoft.studio.field.label" value="type"/>
// 		<property name="com.jaspersoft.studio.field.tree.path" value="cards"/>
// 	</field>
// 	<field name="priority" class="java.lang.String">
// 		<property name="com.jaspersoft.studio.field.name" value="priority"/>
// 		<property name="com.jaspersoft.studio.field.label" value="priority"/>
// 		<property name="com.jaspersoft.studio.field.tree.path" value="cards"/>
// 	</field>
// 	<field name="origin" class="java.lang.String">
// 		<property name="com.jaspersoft.studio.field.name" value="origin"/>
// 		<property name="com.jaspersoft.studio.field.label" value="origin"/>
// 		<property name="com.jaspersoft.studio.field.tree.path" value="cards"/>
// 	</field>
// 	<field name="destination" class="java.lang.String">
// 		<property name="com.jaspersoft.studio.field.name" value="destination"/>
// 		<property name="com.jaspersoft.studio.field.label" value="destination"/>
// 		<property name="com.jaspersoft.studio.field.tree.path" value="cards"/>
// 	</field>
// 	<field name="quantity" class="java.lang.Integer">
// 		<property name="com.jaspersoft.studio.field.name" value="quantity"/>
// 		<property name="com.jaspersoft.studio.field.label" value="quantity"/>
// 		<property name="com.jaspersoft.studio.field.tree.path" value="cards"/>
// 	</field>
// 	<field name="revenue" class="java.lang.Integer">
// 		<property name="com.jaspersoft.studio.field.name" value="revenue"/>
// 		<property name="com.jaspersoft.studio.field.label" value="revenue"/>
// 		<property name="com.jaspersoft.studio.field.tree.path" value="cards"/>
// 	</field>
// 	<field name="created_at" class="java.sql.Timestamp">
// 		<property name="com.jaspersoft.studio.field.name" value="created_at"/>
// 		<property name="com.jaspersoft.studio.field.label" value="created_at"/>
// 		<property name="com.jaspersoft.studio.field.tree.path" value="cards"/>
// 	</field>
// 	<field name="updated_at" class="java.sql.Timestamp">
// 		<property name="com.jaspersoft.studio.field.name" value="updated_at"/>
// 		<property name="com.jaspersoft.studio.field.label" value="updated_at"/>
// 		<property name="com.jaspersoft.studio.field.tree.path" value="cards"/>
// 	</field>
// 	<title>
// 		<band height="79" splitType="Stretch">
// 			<staticText>
// 				<reportElement x="0" y="0" width="760" height="79" uuid="975ebb93-b85e-40b4-bfc8-4075c9ff354a"/>
// 				<textElement textAlignment="Center" verticalAlignment="Middle">
// 					<font size="54"/>
// 				</textElement>
// 				<text><![CDATA[Hello]]></text>
// 			</staticText>
// 		</band>
// 	</title>
// 	<detail>
// 		<band height="500" splitType="Stretch">
// 			<componentElement>
// 				<reportElement x="0" y="0" width="760" height="450" uuid="586a4272-5e17-4988-b7bd-a2925b3f5bdb">
// 					<property name="com.jaspersoft.studio.layout" value="com.jaspersoft.studio.editor.layout.VerticalRowLayout"/>
// 					<property name="com.jaspersoft.studio.table.style.table_header" value="Table_TH"/>
// 					<property name="com.jaspersoft.studio.table.style.column_header" value="Table_CH"/>
// 					<property name="com.jaspersoft.studio.table.style.detail" value="Table_TD"/>
// 				</reportElement>
// 				<jr:table xmlns:jr="http://jasperreports.sourceforge.net/jasperreports/components" xsi:schemaLocation="http://jasperreports.sourceforge.net/jasperreports/components http://jasperreports.sourceforge.net/xsd/components.xsd">
// 					<datasetRun subDataset="Dataset1" uuid="b340a5d9-d449-4043-9b7d-cc44c0648673">
// 						<connectionExpression><![CDATA[$P{REPORT_CONNECTION}]]></connectionExpression>
// 					</datasetRun>
// 					<jr:column width="40" uuid="1a69ceba-35dd-4bef-a223-313924b2f49d">
// 						<property name="com.jaspersoft.studio.components.table.model.column.name" value="Column1"/>
// 						<jr:columnHeader style="Table_CH" height="36">
// 							<staticText>
// 								<reportElement x="0" y="0" width="40" height="36" uuid="cfeb4ded-fda7-4788-917a-ac02e206a4fa"/>
// 								<textElement textAlignment="Center" verticalAlignment="Middle"/>
// 								<text><![CDATA[id]]></text>
// 							</staticText>
// 						</jr:columnHeader>
// 						<jr:detailCell style="Table_TD" height="36">
// 							<textField>
// 								<reportElement x="0" y="0" width="40" height="36" uuid="e8490680-10c7-408f-a879-95a2386a66b8"/>
// 								<textElement textAlignment="Center" verticalAlignment="Middle"/>
// 								<textFieldExpression><![CDATA[$F{id}]]></textFieldExpression>
// 							</textField>
// 						</jr:detailCell>
// 					</jr:column>
// 					<jr:column width="60" uuid="b0bea22b-2afc-4438-9a44-cf61b13637be">
// 						<property name="com.jaspersoft.studio.components.table.model.column.name" value="Column2"/>
// 						<jr:columnHeader style="Table_CH" height="36">
// 							<staticText>
// 								<reportElement x="0" y="0" width="60" height="36" uuid="b5e88409-9bda-4fc5-8c08-6aae47ff44fd"/>
// 								<textElement textAlignment="Center" verticalAlignment="Middle"/>
// 								<text><![CDATA[type]]></text>
// 							</staticText>
// 						</jr:columnHeader>
// 						<jr:detailCell style="Table_TD" height="36">
// 							<textField>
// 								<reportElement x="0" y="0" width="60" height="36" uuid="42b9f66d-d9c6-4295-b027-eab118080b77"/>
// 								<textElement textAlignment="Center" verticalAlignment="Middle"/>
// 								<textFieldExpression><![CDATA[$F{type}]]></textFieldExpression>
// 							</textField>
// 						</jr:detailCell>
// 					</jr:column>
// 					<jr:column width="70" uuid="b6e6571d-836f-4b9b-bf5f-6596268cc36d">
// 						<property name="com.jaspersoft.studio.components.table.model.column.name" value="Column3"/>
// 						<jr:columnHeader style="Table_CH" height="36">
// 							<staticText>
// 								<reportElement x="0" y="0" width="70" height="36" uuid="c95417d5-3534-46a6-a5a1-849e3367623d"/>
// 								<textElement textAlignment="Center" verticalAlignment="Middle"/>
// 								<text><![CDATA[priority]]></text>
// 							</staticText>
// 						</jr:columnHeader>
// 						<jr:detailCell style="Table_TD" height="36">
// 							<textField>
// 								<reportElement x="0" y="0" width="70" height="36" uuid="3446cbf2-ed01-4075-af3d-59fc089afda0"/>
// 								<textElement textAlignment="Center" verticalAlignment="Middle"/>
// 								<textFieldExpression><![CDATA[$F{priority}]]></textFieldExpression>
// 							</textField>
// 						</jr:detailCell>
// 					</jr:column>
// 					<jr:column width="72" uuid="b30b5417-8df8-48c9-9ad5-c9175bdf24fd">
// 						<property name="com.jaspersoft.studio.components.table.model.column.name" value="Column4"/>
// 						<jr:columnHeader style="Table_CH" height="36">
// 							<staticText>
// 								<reportElement x="0" y="0" width="72" height="36" uuid="488066ac-720b-4e0d-85cb-e62da4680b4d"/>
// 								<textElement textAlignment="Center" verticalAlignment="Middle"/>
// 								<text><![CDATA[origin]]></text>
// 							</staticText>
// 						</jr:columnHeader>
// 						<jr:detailCell style="Table_TD" height="36">
// 							<textField>
// 								<reportElement x="0" y="0" width="72" height="36" uuid="2adb6564-ca81-4a57-ad42-ee108edf5fbc"/>
// 								<textElement textAlignment="Center" verticalAlignment="Middle"/>
// 								<textFieldExpression><![CDATA[$F{origin}]]></textFieldExpression>
// 							</textField>
// 						</jr:detailCell>
// 					</jr:column>
// 					<jr:column width="72" uuid="87212f27-db96-49c0-8cd5-e2294bf4d44f">
// 						<property name="com.jaspersoft.studio.components.table.model.column.name" value="Column5"/>
// 						<jr:columnHeader style="Table_CH" height="36">
// 							<staticText>
// 								<reportElement x="0" y="0" width="72" height="36" uuid="6a24a45c-4cb9-424c-b921-27706d882f3e"/>
// 								<textElement textAlignment="Center" verticalAlignment="Middle"/>
// 								<text><![CDATA[destination]]></text>
// 							</staticText>
// 						</jr:columnHeader>
// 						<jr:detailCell style="Table_TD" height="36">
// 							<textField>
// 								<reportElement x="0" y="0" width="72" height="36" uuid="ed666b07-d207-4463-88b2-431fa00a4439"/>
// 								<textElement textAlignment="Center" verticalAlignment="Middle"/>
// 								<textFieldExpression><![CDATA[$F{destination}]]></textFieldExpression>
// 							</textField>
// 						</jr:detailCell>
// 					</jr:column>
// 					<jr:column width="56" uuid="f8dffd5f-df8f-4053-ac73-c29aed900352">
// 						<property name="com.jaspersoft.studio.components.table.model.column.name" value="Column6"/>
// 						<jr:columnHeader style="Table_CH" height="36">
// 							<staticText>
// 								<reportElement x="0" y="0" width="56" height="36" uuid="ca4fb956-bb65-44f2-b093-711de580851f"/>
// 								<textElement textAlignment="Center" verticalAlignment="Middle"/>
// 								<text><![CDATA[quantity]]></text>
// 							</staticText>
// 						</jr:columnHeader>
// 						<jr:detailCell style="Table_TD" height="36">
// 							<textField>
// 								<reportElement x="0" y="0" width="56" height="36" uuid="12b09021-6119-4169-bc29-2bc64e3e245e"/>
// 								<textElement textAlignment="Center" verticalAlignment="Middle"/>
// 								<textFieldExpression><![CDATA[$F{quantity}]]></textFieldExpression>
// 							</textField>
// 						</jr:detailCell>
// 					</jr:column>
//                     <jr:column width="200" uuid="96ca6586-b171-42e9-8255-a67addf4d111">
//                         <property name="com.jaspersoft.studio.components.table.model.column.name" value="Column7"/>
//                         <property name="net.sf.jasperreports.export.xls.column.locked" value="true"/>
//                         <jr:columnHeader style="Table_CH" height="36">
//                             <staticText>
//                                 <reportElement x="0" y="0" width="200" height="36" uuid="48f5a1e7-bd79-4c9a-8d3c-59c88d5e1c2c"/>
//                                 <textElement textAlignment="Center" verticalAlignment="Middle"/>
//                                 <text><![CDATA[revenue]]></text>
//                             </staticText>
//                         </jr:columnHeader>
//                         <jr:detailCell style="Table_TD" height="36">
//                             <textField textAdjust="StretchHeight">
//                                 <reportElement x="0" y="0" width="200" height="36" uuid="3f785951-e61c-4144-9efb-ddf94309ad49">
//                                     <property name="net.sf.jasperreports.export.xls.cell.locked" value="true"/>
//                                 </reportElement>
//                                 <textElement textAlignment="Center" verticalAlignment="Middle"/>
//                                 <textFieldExpression><![CDATA[new java.text.DecimalFormat("#,##0.###################################").format(new java.math.BigDecimal($F{revenue}))]]></textFieldExpression>
//                             </textField>
//                         </jr:detailCell>
//                     </jr:column>
// 					<jr:column width="88" uuid="6d2dcb84-c6b2-4a79-99d9-796a8318d744">
// 						<property name="com.jaspersoft.studio.components.table.model.column.name" value="Column8"/>
// 						<jr:columnHeader style="Table_CH" height="36">
// 							<staticText>
// 								<reportElement x="0" y="0" width="88" height="36" uuid="d0482453-7df3-40a2-aaee-2f957a0b4035"/>
// 								<textElement textAlignment="Center" verticalAlignment="Middle"/>
// 								<text><![CDATA[created_at]]></text>
// 							</staticText>
// 						</jr:columnHeader>
// 						<jr:detailCell style="Table_TD" height="36">
// 							<textField pattern="MMM d, yyyy h:mm:ss a">
// 								<reportElement x="0" y="0" width="88" height="36" uuid="1a68c8fd-16d2-4203-80fc-1c9ae74b8be1"/>
// 								<textElement textAlignment="Center" verticalAlignment="Middle"/>
// 								<textFieldExpression><![CDATA[$F{created_at}]]></textFieldExpression>
// 							</textField>
// 						</jr:detailCell>
// 					</jr:column>
// 					<jr:column width="100" uuid="362a6fee-6583-4e3e-ba6d-742c591f8b01">
// 						<property name="com.jaspersoft.studio.components.table.model.column.name" value="Column9"/>
// 						<jr:columnHeader style="Table_CH" height="36">
// 							<staticText>
// 								<reportElement x="0" y="0" width="100" height="36" uuid="8cf22a6f-8e0d-4e2b-af39-780ceafaf380"/>
// 								<textElement textAlignment="Center" verticalAlignment="Middle"/>
// 								<text><![CDATA[updated_at]]></text>
// 							</staticText>
// 						</jr:columnHeader>
// 						<jr:detailCell style="Table_TD" height="36">
// 							<textField pattern="MMM d, yyyy h:mm:ss a">
// 								<reportElement x="0" y="0" width="100" height="36" uuid="ccf5366e-1775-4fb3-b956-11321d1f1c64"/>
// 								<textElement textAlignment="Center" verticalAlignment="Middle"/>
// 								<textFieldExpression><![CDATA[$F{updated_at}]]></textFieldExpression>
// 							</textField>
// 						</jr:detailCell>
// 					</jr:column>
// 				</jr:table>
// 			</componentElement>
// 		</band>
// 	</detail>
// </jasperReport>

/* 0.23711659999999998 */
/* 0.42423259595954545756543255969612345 */

/* Alasannya kenapa dirounding? Ini dikarenakan settingan ukuran presisi decimal yg dapat disimpan dalam database pada suatu kolom */
/* Karena partner_rate ini keelihatannya fixed di 7 digit, maka di ukuran partner_rate ini sebenarnya tidak bisa menunjukkan lebih dari 7 digit karena pasti akan diround */
/* Makanya kayak ada kasus sblmnya, itu dipotong menjadi 7 digit */
