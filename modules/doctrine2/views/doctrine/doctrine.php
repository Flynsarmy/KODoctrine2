Generates entities/proxies/repositories. This will <strong>NOT</strong> delete your entity files, it will only add missing fields/methods from your schema files. A backup of your old entity will be generated with a ~ extension.
<form action="" method="POST">
	<input type="submit" name="schema" value="Load Schema"><br />
</form>

This is completely safe and will not modify your schema/files in any way.
<form action="" method="POST">
	<input type="submit" name="validate" value="Validate Schema"><br />
</form>

Create/Update DB tables. This will attempt to update the DB if the tables already exist.
<form action="" method="POST">
	<input type="submit" name="tables-sql" value="Show Modifications">
	<input type="submit" name="tables" value="Create Tables"><br />
</form>

<!--
This will delete all existing data!<br />
<form action="" method="POST">
	<input type="submit" name="data" value="Load Fixtures"><br />
</form>
-->