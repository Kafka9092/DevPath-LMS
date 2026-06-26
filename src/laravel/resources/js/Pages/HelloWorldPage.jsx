import {Switch} from "@radix-ui/react-switch";
import { Button } from '@/components/ui/button';
export default function HelloWorldPage({ username }) {
    return (
        <>
        <h1>Привет, {username}!</h1>
        <Switch defaultChecked />
        <Button defaultChecked/>
</>
    );


}
